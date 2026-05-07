<?php

namespace App\Support\Billing;

use App\Enums\PlanFeatureKey;
use App\Enums\TenantRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\UsageCounter;
use Illuminate\Validation\ValidationException;

class SubscriptionFeatureGate
{
    public function currentSubscription(Tenant|string $tenant): ?Subscription
    {
        return Subscription::query()
            ->withoutGlobalScope('current_tenant')
            ->with('plan.features')
            ->where('tenant_id', $this->tenantId($tenant))
            ->where('is_current', true)
            ->first();
    }

    public function value(Tenant|string $tenant, PlanFeatureKey|string $key): mixed
    {
        $key = $this->featureKey($key);
        $subscription = $this->currentSubscription($tenant);

        if (! $subscription?->allowsFeatureAccess()) {
            return null;
        }

        return $subscription->plan->featureValue($key);
    }

    public function hasAccess(Tenant|string $tenant): bool
    {
        return $this->currentSubscription($tenant)?->allowsFeatureAccess() ?? false;
    }

    public function enabled(Tenant|string $tenant, PlanFeatureKey|string $key): bool
    {
        return (bool) $this->value($tenant, $key);
    }

    public function limit(Tenant|string $tenant, PlanFeatureKey|string $key): ?int
    {
        $value = $this->value($tenant, $key);

        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? null : 0;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    public function usage(Tenant|string $tenant, PlanFeatureKey|string $key): int
    {
        $tenantId = $this->tenantId($tenant);
        $key = $this->featureKey($key);

        return match ($key) {
            PlanFeatureKey::MaxProducts => Product::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $tenantId)
                ->count(),
            PlanFeatureKey::MaxOrdersPerMonth => Order::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('created_at', '<', now()->copy()->startOfMonth()->addMonth())
                ->count(),
            PlanFeatureKey::MaxStaffUsers => TenantUser::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $tenantId)
                ->where('role', '!=', TenantRole::Owner->value)
                ->count(),
            default => $this->counterUsage($tenantId, $key),
        };
    }

    public function ensureWithinLimit(Tenant|string $tenant, PlanFeatureKey|string $key, int $increment = 1, ?int $currentUsage = null): void
    {
        $key = $this->featureKey($key);

        if (! $this->hasAccess($tenant)) {
            throw ValidationException::withMessages([
                'subscription' => __('An active subscription is required.'),
            ]);
        }

        $limit = $this->limit($tenant, $key);

        if ($limit === null) {
            return;
        }

        $currentUsage ??= $this->usage($tenant, $key);

        if (($currentUsage + $increment) <= $limit) {
            return;
        }

        throw ValidationException::withMessages([
            $key->value => __('The :feature plan limit has been reached.', ['feature' => $key->getLabel()]),
        ]);
    }

    public function withinLimit(Tenant|string $tenant, PlanFeatureKey|string $key, int $increment = 1, ?int $currentUsage = null): bool
    {
        try {
            $this->ensureWithinLimit($tenant, $key, $increment, $currentUsage);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function counterUsage(string $tenantId, PlanFeatureKey $key): int
    {
        return UsageCounter::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->where('key', $key->value)
            ->where('period_start', '<=', now()->toDateString())
            ->where('period_end', '>=', now()->toDateString())
            ->sum('used');
    }

    private function tenantId(Tenant|string $tenant): string
    {
        return $tenant instanceof Tenant ? $tenant->id : $tenant;
    }

    private function featureKey(PlanFeatureKey|string $key): PlanFeatureKey
    {
        if ($key instanceof PlanFeatureKey) {
            return $key;
        }

        $featureKey = PlanFeatureKey::tryFrom($key);

        if ($featureKey === null) {
            throw ValidationException::withMessages([
                'feature' => __('Unknown plan feature [:feature].', ['feature' => $key]),
            ]);
        }

        return $featureKey;
    }
}
