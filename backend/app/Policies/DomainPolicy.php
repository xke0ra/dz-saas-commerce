<?php

namespace App\Policies;

use App\Enums\PlanFeatureKey;
use App\Enums\TenantPermission;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\SubscriptionFeatureGate;
use App\Support\Tenancy\CurrentTenant;

class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::DomainsView);
    }

    public function view(User $user, Domain $domain): bool
    {
        return $user->hasTenantPermission($domain->tenant_id, TenantPermission::DomainsView);
    }

    public function create(User $user): bool
    {
        return $this->canManageTenantDomains($user, app(CurrentTenant::class)->get());
    }

    public function update(User $user, Domain $domain): bool
    {
        return $this->canManageTenantDomains($user, $domain->tenant_id);
    }

    public function delete(User $user, Domain $domain): bool
    {
        return $this->canManageTenantDomains($user, $domain->tenant_id);
    }

    private function canManageTenantDomains(User $user, Tenant|string|null $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        if (! $user->hasTenantPermission($tenant, TenantPermission::DomainsManage)) {
            return false;
        }

        return app(SubscriptionFeatureGate::class)->enabled($tenant, PlanFeatureKey::CustomDomain);
    }
}
