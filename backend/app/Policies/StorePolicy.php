<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::StoresView);
    }

    public function view(User $user, Store $store): bool
    {
        return $user->hasTenantPermission($store->tenant_id, TenantPermission::StoresView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::StoresCreate);
    }

    public function update(User $user, Store $store): bool
    {
        return $user->hasTenantPermission($store->tenant_id, TenantPermission::StoresUpdate);
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->hasTenantPermission($store->tenant_id, TenantPermission::StoresDelete);
    }
}
