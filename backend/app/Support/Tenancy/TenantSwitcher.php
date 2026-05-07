<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TenantSwitcher
{
    public const SESSION_KEY = 'dz_saas_commerce.vendor_tenant_id';

    /**
     * @return Collection<int, Tenant>
     */
    public function availableTenantsFor(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Tenant::query()
                ->orderBy('name')
                ->get();
        }

        return $user->tenants()
            ->orderBy('tenants.name')
            ->get();
    }

    public function findAvailableTenantFor(User $user, string $tenantId): ?Tenant
    {
        $query = Tenant::query()->whereKey($tenantId);

        if (! $user->isSuperAdmin()) {
            $query->whereHas('users', fn ($users) => $users->whereKey($user->getKey()));
        }

        return $query->first();
    }
}
