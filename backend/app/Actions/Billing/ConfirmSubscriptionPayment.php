<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\StoreStatus;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSubscriptionPayment
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(SubscriptionPayment $payment, User $confirmedBy): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $confirmedBy): SubscriptionPayment {
            $payment = SubscriptionPayment::query()
                ->withoutGlobalScope('current_tenant')
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== SubscriptionPaymentStatus::Pending) {
                throw ValidationException::withMessages([
                    'payment' => __('Only pending subscription payments can be confirmed.'),
                ]);
            }

            $invoice = Invoice::query()
                ->withoutGlobalScope('current_tenant')
                ->whereKey($payment->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            $confirmedTotalBefore = SubscriptionPayment::query()
                ->withoutGlobalScope('current_tenant')
                ->where('invoice_id', $invoice->id)
                ->where('status', SubscriptionPaymentStatus::Confirmed)
                ->sum('amount_minor');

            if (($confirmedTotalBefore + $payment->amount_minor) > $invoice->total_minor) {
                throw ValidationException::withMessages([
                    'payment' => __('Confirming this payment would exceed the invoice total.'),
                ]);
            }

            $payment->update([
                'status' => SubscriptionPaymentStatus::Confirmed,
                'confirmed_by' => $confirmedBy->id,
                'confirmed_at' => now(),
            ]);

            $paidAmount = $confirmedTotalBefore + $payment->amount_minor;
            $balance = $invoice->total_minor - $paidAmount;
            $invoiceStatus = $balance === 0 ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid;

            $invoice->update([
                'status' => $invoiceStatus,
                'paid_amount_minor' => $paidAmount,
                'balance_minor' => $balance,
                'paid_at' => $invoiceStatus === InvoiceStatus::Paid ? now() : null,
            ]);

            $this->activateSubscriptionWhenInvoiceIsPaid($invoice);

            $this->auditLogger->record(
                event: 'subscription_payment.confirmed',
                auditable: $payment,
                actor: $confirmedBy,
                newValues: [
                    'status' => $payment->status,
                    'confirmed_by' => $confirmedBy->id,
                    'confirmed_at' => $payment->confirmed_at,
                ],
            );

            if ($invoiceStatus === InvoiceStatus::Paid) {
                $this->auditLogger->record(
                    event: 'invoice.paid',
                    auditable: $invoice,
                    actor: $confirmedBy,
                    newValues: [
                        'status' => $invoice->status,
                        'paid_amount_minor' => $invoice->paid_amount_minor,
                        'paid_at' => $invoice->paid_at,
                    ],
                );
            }

            return $payment->refresh();
        });
    }

    private function activateSubscriptionWhenInvoiceIsPaid(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Paid || $invoice->subscription_id === null) {
            return;
        }

        $subscription = Subscription::query()
            ->withoutGlobalScope('current_tenant')
            ->whereKey($invoice->subscription_id)
            ->where('is_current', true)
            ->first();

        if ($subscription === null || $subscription->status === SubscriptionStatus::Cancelled) {
            return;
        }

        $updates = [
            'status' => SubscriptionStatus::Active,
            'grace_ends_at' => null,
            'ends_at' => null,
        ];
        $periodRenewed = false;

        if (
            $invoice->type === InvoiceType::SubscriptionRenewal
            && $invoice->billing_period_starts_at !== null
            && $invoice->billing_period_ends_at !== null
            && $invoice->billing_period_ends_at->greaterThan($subscription->current_period_ends_at)
        ) {
            $oldPeriodStartsAt = $subscription->current_period_starts_at;
            $oldPeriodEndsAt = $subscription->current_period_ends_at;
            $updates['current_period_starts_at'] = $invoice->billing_period_starts_at;
            $updates['current_period_ends_at'] = $invoice->billing_period_ends_at;
            $periodRenewed = true;
        }

        $oldStatus = $subscription->status;
        $subscription->update($updates);
        $subscription->refresh();

        if ($periodRenewed) {
            $this->auditLogger->record(
                event: 'subscription.renewed',
                auditable: $subscription,
                oldValues: [
                    'status' => $oldStatus,
                    'current_period_starts_at' => $oldPeriodStartsAt,
                    'current_period_ends_at' => $oldPeriodEndsAt,
                ],
                newValues: [
                    'status' => $subscription->status,
                    'current_period_starts_at' => $subscription->current_period_starts_at,
                    'current_period_ends_at' => $subscription->current_period_ends_at,
                ],
                metadata: ['invoice_id' => $invoice->id],
            );
        }

        $this->reactivateBillingSuspendedTenant($subscription);
        $this->reactivateBillingSuspendedStores($subscription);
    }

    private function reactivateBillingSuspendedTenant(Subscription $subscription): void
    {
        $tenant = Tenant::query()
            ->whereKey($subscription->tenant_id)
            ->first();

        if ($tenant === null || $tenant->status !== TenantStatus::Suspended) {
            return;
        }

        $settings = $tenant->settings ?? [];

        if (($settings['billing_suspension']['subscription_id'] ?? null) !== $subscription->id) {
            return;
        }

        unset($settings['billing_suspension']);

        $tenant->update([
            'status' => TenantStatus::Active,
            'settings' => $settings,
        ]);
    }

    private function reactivateBillingSuspendedStores(Subscription $subscription): void
    {
        Store::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $subscription->tenant_id)
            ->where('status', StoreStatus::Suspended)
            ->orderBy('id')
            ->chunkById(100, function ($stores) use ($subscription): void {
                foreach ($stores as $store) {
                    /** @var Store $store */
                    $settings = $store->settings ?? [];

                    if (($settings['billing_suspension']['subscription_id'] ?? null) !== $subscription->id) {
                        continue;
                    }

                    unset($settings['billing_suspension']);

                    $store->update([
                        'status' => StoreStatus::Active,
                        'settings' => $settings,
                    ]);
                }
            });
    }
}
