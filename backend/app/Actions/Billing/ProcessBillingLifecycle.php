<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\StoreStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionGracePeriodStartedNotification;
use App\Notifications\SubscriptionRenewalReminderNotification;
use App\Notifications\SubscriptionSuspendedNotification;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessBillingLifecycle
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly IssueSubscriptionInvoice $issueSubscriptionInvoice,
    ) {}

    /**
     * @return array<string, int>
     */
    public function handle(?CarbonInterface $now = null): array
    {
        $now = $now === null ? now() : Carbon::parse($now);
        $counts = $this->emptyCounts();

        $this->markOverdueInvoices($now, $counts);
        $this->issueRenewalInvoices($now, $counts);
        $this->sendRenewalReminders($now, $counts);
        $this->processEndedPeriods($now, $counts);

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
            'invoices_marked_overdue' => 0,
            'subscriptions_marked_past_due' => 0,
            'renewal_invoices_issued' => 0,
            'renewal_reminders_sent' => 0,
            'grace_periods_started' => 0,
            'subscriptions_suspended' => 0,
            'tenants_suspended' => 0,
            'stores_suspended' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function markOverdueInvoices(Carbon $now, array &$counts): void
    {
        Invoice::query()
            ->withoutGlobalScope('current_tenant')
            ->with('subscription')
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use (&$counts): void {
                foreach ($invoices as $invoice) {
                    DB::transaction(function () use ($invoice, &$counts): void {
                        /** @var Invoice $invoice */
                        $invoice = Invoice::query()
                            ->withoutGlobalScope('current_tenant')
                            ->whereKey($invoice->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();

                        if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true)) {
                            return;
                        }

                        $oldStatus = $invoice->status;
                        $invoice->update(['status' => InvoiceStatus::Overdue]);
                        $counts['invoices_marked_overdue']++;

                        $this->auditLogger->record(
                            event: 'invoice.overdue',
                            auditable: $invoice,
                            oldValues: ['status' => $oldStatus],
                            newValues: ['status' => InvoiceStatus::Overdue],
                        );

                        $subscription = $invoice->subscription;

                        if (
                            $subscription instanceof Subscription
                            && $subscription->is_current
                            && in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true)
                        ) {
                            $oldSubscriptionStatus = $subscription->status;
                            $subscription->update(['status' => SubscriptionStatus::PastDue]);
                            $counts['subscriptions_marked_past_due']++;

                            $this->auditLogger->record(
                                event: 'subscription.past_due',
                                auditable: $subscription,
                                oldValues: ['status' => $oldSubscriptionStatus],
                                newValues: ['status' => SubscriptionStatus::PastDue],
                                metadata: ['invoice_id' => $invoice->id],
                            );
                        }
                    });
                }
            });
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function issueRenewalInvoices(Carbon $now, array &$counts): void
    {
        $windowEndsAt = $now->copy()->addDays($this->renewalInvoiceDaysBeforePeriodEnd());

        Subscription::query()
            ->withoutGlobalScope('current_tenant')
            ->with('plan')
            ->where('is_current', true)
            ->whereIn('status', [
                SubscriptionStatus::Trialing,
                SubscriptionStatus::Active,
                SubscriptionStatus::GracePeriod,
            ])
            ->where('current_period_ends_at', '<=', $windowEndsAt)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($now, &$counts): void {
                foreach ($subscriptions as $subscription) {
                    /** @var Subscription $subscription */
                    $invoice = $this->issueSubscriptionInvoice->handle(
                        subscription: $subscription,
                        type: InvoiceType::SubscriptionRenewal,
                        issuedAt: $now,
                        billingPeriodStartsAt: $subscription->current_period_ends_at,
                        dueAt: $subscription->current_period_ends_at,
                    );

                    if ($invoice?->wasRecentlyCreated === true) {
                        $counts['renewal_invoices_issued']++;
                    }
                }
            });
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function sendRenewalReminders(Carbon $now, array &$counts): void
    {
        foreach ($this->reminderDays() as $days) {
            Subscription::query()
                ->withoutGlobalScope('current_tenant')
                ->with(['tenant.owner', 'plan'])
                ->where('is_current', true)
                ->whereIn('status', [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::GracePeriod])
                ->whereDate('current_period_ends_at', $now->copy()->addDays($days)->toDateString())
                ->orderBy('id')
                ->chunkById(100, function ($subscriptions) use ($days, &$counts): void {
                    foreach ($subscriptions as $subscription) {
                        /** @var Subscription $subscription */
                        $metadata = $subscription->metadata ?? [];
                        $reminderKey = $this->reminderMetadataKey($subscription, $days);

                        if (($metadata[$reminderKey] ?? false) === true) {
                            continue;
                        }

                        $owner = $subscription->tenant->owner;

                        if ($owner === null) {
                            continue;
                        }

                        $owner->notify(new SubscriptionRenewalReminderNotification($subscription, $days));

                        $metadata[$reminderKey] = true;
                        $subscription->update(['metadata' => $metadata]);
                        $counts['renewal_reminders_sent']++;

                        $this->auditLogger->record(
                            event: 'subscription.renewal_reminder_sent',
                            auditable: $subscription,
                            metadata: ['days_until_renewal' => $days],
                        );
                    }
                });
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function processEndedPeriods(Carbon $now, array &$counts): void
    {
        Subscription::query()
            ->withoutGlobalScope('current_tenant')
            ->with(['tenant.owner', 'tenant.stores', 'plan'])
            ->where('is_current', true)
            ->whereIn('status', [
                SubscriptionStatus::Trialing,
                SubscriptionStatus::Active,
                SubscriptionStatus::PastDue,
                SubscriptionStatus::GracePeriod,
            ])
            ->where('current_period_ends_at', '<', $now)
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($now, &$counts): void {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription, $now, &$counts): void {
                        /** @var Subscription $subscription */
                        $subscription = Subscription::query()
                            ->withoutGlobalScope('current_tenant')
                            ->with(['tenant.owner', 'tenant.stores', 'plan'])
                            ->whereKey($subscription->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();

                        if ($subscription->status === SubscriptionStatus::GracePeriod) {
                            if ($subscription->grace_ends_at !== null && $subscription->grace_ends_at->lessThan($now)) {
                                $this->suspendSubscription($subscription, $counts);
                            }

                            return;
                        }

                        $graceDays = $this->gracePeriodDays();

                        if ($graceDays <= 0) {
                            $this->suspendSubscription($subscription, $counts);

                            return;
                        }

                        $oldStatus = $subscription->status;
                        $subscription->update([
                            'status' => SubscriptionStatus::GracePeriod,
                            'grace_ends_at' => $now->copy()->addDays($graceDays),
                        ]);
                        $subscription->refresh()->load(['tenant.owner', 'plan']);
                        $counts['grace_periods_started']++;

                        $this->auditLogger->record(
                            event: 'subscription.grace_period_started',
                            auditable: $subscription,
                            oldValues: ['status' => $oldStatus],
                            newValues: [
                                'status' => SubscriptionStatus::GracePeriod,
                                'grace_ends_at' => $subscription->grace_ends_at,
                            ],
                        );

                        $subscription->tenant->owner?->notify(new SubscriptionGracePeriodStartedNotification($subscription));
                    });
                }
            });
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function suspendSubscription(Subscription $subscription, array &$counts): void
    {
        if ($subscription->status === SubscriptionStatus::Suspended) {
            return;
        }

        $oldStatus = $subscription->status;
        $metadata = $subscription->metadata ?? [];

        $subscription->update([
            'status' => SubscriptionStatus::Suspended,
            'ends_at' => now(),
            'metadata' => [
                ...$metadata,
                'billing.suspended_at' => now()->toDateTimeString(),
            ],
        ]);
        $subscription->refresh()->load(['tenant.owner', 'tenant.stores', 'plan']);
        $counts['subscriptions_suspended']++;

        $this->auditLogger->record(
            event: 'subscription.suspended',
            auditable: $subscription,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => SubscriptionStatus::Suspended],
        );

        if ((bool) config('billing.suspend_tenant_when_subscription_suspended', true)) {
            $this->suspendTenant($subscription->tenant, $subscription, $counts);
        }

        if ((bool) config('billing.suspend_stores_when_subscription_suspended', true)) {
            $this->suspendStores($subscription->tenant, $subscription, $counts);
        }

        if (($metadata['billing.suspended_notification_sent'] ?? false) !== true) {
            $subscription->tenant->owner?->notify(new SubscriptionSuspendedNotification($subscription));

            $subscription->update([
                'metadata' => [
                    ...($subscription->metadata ?? []),
                    'billing.suspended_notification_sent' => true,
                ],
            ]);
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function suspendTenant(Tenant $tenant, Subscription $subscription, array &$counts): void
    {
        if ($tenant->status === TenantStatus::Suspended) {
            return;
        }

        $settings = $tenant->settings ?? [];

        $tenant->update([
            'status' => TenantStatus::Suspended,
            'settings' => [
                ...$settings,
                'billing_suspension' => [
                    'subscription_id' => $subscription->id,
                    'suspended_at' => now()->toDateTimeString(),
                ],
            ],
        ]);
        $counts['tenants_suspended']++;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function suspendStores(Tenant $tenant, Subscription $subscription, array &$counts): void
    {
        Store::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenant->id)
            ->where('status', StoreStatus::Active)
            ->orderBy('id')
            ->chunkById(100, function ($stores) use ($subscription, &$counts): void {
                foreach ($stores as $store) {
                    /** @var Store $store */
                    $settings = $store->settings ?? [];

                    $store->update([
                        'status' => StoreStatus::Suspended,
                        'settings' => [
                            ...$settings,
                            'billing_suspension' => [
                                'subscription_id' => $subscription->id,
                                'suspended_at' => now()->toDateTimeString(),
                            ],
                        ],
                    ]);
                    $counts['stores_suspended']++;
                }
            });
    }

    /**
     * @return array<int, int>
     */
    private function reminderDays(): array
    {
        return collect(config('billing.renewal_reminder_days', [7, 3, 1]))
            ->map(fn (mixed $days): int => (int) $days)
            ->filter(fn (int $days): bool => $days > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function reminderMetadataKey(Subscription $subscription, int $days): string
    {
        return 'billing.reminders_sent.'
            .$subscription->current_period_ends_at->toDateString()
            .'.'.$days;
    }

    private function gracePeriodDays(): int
    {
        return max(0, (int) config('billing.grace_period_days', 7));
    }

    private function renewalInvoiceDaysBeforePeriodEnd(): int
    {
        return max(0, (int) config('billing.renewal_invoice_days_before_period_end', 7));
    }
}
