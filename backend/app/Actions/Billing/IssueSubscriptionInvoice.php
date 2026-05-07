<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Billing\BillingPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IssueSubscriptionInvoice
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BillingPeriod $billingPeriod,
    ) {}

    public function handle(
        Subscription $subscription,
        InvoiceType|string $type,
        ?CarbonInterface $issuedAt = null,
        ?CarbonInterface $billingPeriodStartsAt = null,
        ?CarbonInterface $billingPeriodEndsAt = null,
        ?CarbonInterface $dueAt = null,
        int $dueDays = 7,
        ?User $actor = null,
    ): ?Invoice {
        $type = $type instanceof InvoiceType ? $type : InvoiceType::from($type);
        $issuedAt = $issuedAt === null ? now() : Carbon::parse($issuedAt);

        return DB::transaction(function () use ($subscription, $type, $issuedAt, $billingPeriodStartsAt, $billingPeriodEndsAt, $dueAt, $dueDays, $actor): ?Invoice {
            $subscription = Subscription::query()
                ->withoutGlobalScope('current_tenant')
                ->with('plan')
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($subscription->plan->price_minor <= 0) {
                return null;
            }

            Tenant::query()
                ->whereKey($subscription->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $periodStartsAt = $billingPeriodStartsAt === null
                ? $this->defaultPeriodStart($subscription, $type)
                : Carbon::parse($billingPeriodStartsAt);
            $periodEndsAt = $billingPeriodEndsAt === null
                ? $this->defaultPeriodEnd($subscription, $type, $periodStartsAt)
                : Carbon::parse($billingPeriodEndsAt);
            $dueAt = $dueAt === null ? $issuedAt->copy()->addDays($dueDays) : Carbon::parse($dueAt);

            $existingInvoice = Invoice::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $subscription->tenant_id)
                ->where('subscription_id', $subscription->id)
                ->where('type', $type)
                ->where('billing_period_starts_at', $periodStartsAt)
                ->first();

            if ($existingInvoice !== null) {
                return $existingInvoice;
            }

            $invoice = Invoice::query()->create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'invoice_number' => $this->nextInvoiceNumber($subscription->tenant_id, $issuedAt),
                'type' => $type,
                'status' => InvoiceStatus::Issued,
                'subtotal_minor' => $subscription->plan->price_minor,
                'tax_minor' => 0,
                'total_minor' => $subscription->plan->price_minor,
                'paid_amount_minor' => 0,
                'balance_minor' => $subscription->plan->price_minor,
                'currency' => $subscription->plan->currency,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
                'billing_period_starts_at' => $periodStartsAt,
                'billing_period_ends_at' => $periodEndsAt,
                'metadata' => [
                    'plan_id' => $subscription->plan->id,
                    'plan_name' => $subscription->plan->name,
                    'billing_interval' => $subscription->plan->billing_interval,
                    'invoice_type' => $type->value,
                ],
            ]);

            $this->auditLogger->record(
                event: 'invoice.issued',
                auditable: $invoice,
                actor: $actor,
                newValues: [
                    'invoice_number' => $invoice->invoice_number,
                    'type' => $invoice->type,
                    'total_minor' => $invoice->total_minor,
                    'status' => $invoice->status,
                    'billing_period_starts_at' => $invoice->billing_period_starts_at,
                    'billing_period_ends_at' => $invoice->billing_period_ends_at,
                ],
            );

            return $invoice;
        });
    }

    private function defaultPeriodStart(Subscription $subscription, InvoiceType $type): Carbon
    {
        return match ($type) {
            InvoiceType::SubscriptionRenewal => Carbon::parse($subscription->current_period_ends_at),
            InvoiceType::SubscriptionInitial => Carbon::parse($subscription->current_period_starts_at),
        };
    }

    private function defaultPeriodEnd(Subscription $subscription, InvoiceType $type, Carbon $periodStartsAt): Carbon
    {
        if (
            $type === InvoiceType::SubscriptionInitial
            && $periodStartsAt->equalTo($subscription->current_period_starts_at)
        ) {
            return Carbon::parse($subscription->current_period_ends_at);
        }

        return $this->billingPeriod->end($periodStartsAt, $subscription->plan->billing_interval);
    }

    private function nextInvoiceNumber(string $tenantId, Carbon $issuedAt): string
    {
        $prefix = 'INV-'.$issuedAt->format('Ym').'-';
        $next = Invoice::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
