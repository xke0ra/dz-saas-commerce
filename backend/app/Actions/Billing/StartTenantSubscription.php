<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceType;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Billing\BillingPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StartTenantSubscription
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BillingPeriod $billingPeriod,
        private readonly IssueSubscriptionInvoice $issueSubscriptionInvoice,
    ) {}

    public function handle(
        Tenant $tenant,
        Plan $plan,
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $trialEndsAt = null,
        ?User $actor = null,
        bool $createInvoice = true,
        int $dueDays = 7,
    ): Subscription {
        $startsAt = $startsAt === null ? now() : Carbon::parse($startsAt);
        $trialEndsAt = $trialEndsAt === null ? null : Carbon::parse($trialEndsAt);
        $periodEndsAt = $this->billingPeriod->end($startsAt, $plan->billing_interval);
        $status = $trialEndsAt !== null && $trialEndsAt->isAfter($startsAt)
            ? SubscriptionStatus::Trialing
            : SubscriptionStatus::Active;

        return DB::transaction(function () use ($tenant, $plan, $startsAt, $trialEndsAt, $periodEndsAt, $status, $actor, $createInvoice, $dueDays): Subscription {
            $currentSubscriptions = Subscription::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $tenant->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->get();

            foreach ($currentSubscriptions as $currentSubscription) {
                $currentSubscription->update([
                    'status' => SubscriptionStatus::Cancelled,
                    'is_current' => false,
                    'cancelled_at' => $startsAt,
                    'ends_at' => $startsAt,
                ]);

                $this->auditLogger->record(
                    event: 'subscription.replaced',
                    auditable: $currentSubscription,
                    actor: $actor,
                    oldValues: ['plan_id' => $currentSubscription->plan_id],
                    newValues: ['plan_id' => $plan->id],
                );
            }

            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'is_current' => true,
                'starts_at' => $startsAt,
                'trial_ends_at' => $trialEndsAt,
                'current_period_starts_at' => $startsAt,
                'current_period_ends_at' => $periodEndsAt,
                'metadata' => [],
            ]);

            $this->auditLogger->record(
                event: 'subscription.started',
                auditable: $subscription,
                actor: $actor,
                newValues: [
                    'plan_id' => $plan->id,
                    'status' => $status,
                    'current_period_ends_at' => $periodEndsAt,
                ],
            );

            if ($createInvoice && $plan->price_minor > 0) {
                $this->issueSubscriptionInvoice->handle(
                    subscription: $subscription,
                    type: InvoiceType::SubscriptionInitial,
                    issuedAt: $startsAt,
                    billingPeriodStartsAt: $startsAt,
                    billingPeriodEndsAt: $periodEndsAt,
                    dueDays: $dueDays,
                    actor: $actor,
                );
            }

            return $subscription->refresh()->load('plan.features');
        });
    }
}
