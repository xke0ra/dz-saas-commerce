<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\StoreSetting;
use App\Models\User;

class StoreSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::StoresView);
    }

    public function view(User $user, StoreSetting $storeSetting): bool
    {
        return $user->hasTenantPermission($storeSetting->tenant_id, TenantPermission::StoresView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::StoresUpdate);
    }

    public function update(User $user, StoreSetting $storeSetting): bool
    {
        return $user->hasTenantPermission($storeSetting->tenant_id, TenantPermission::StoresUpdate);
    }

    public function delete(User $user, StoreSetting $storeSetting): bool
    {
        return $user->hasTenantPermission($storeSetting->tenant_id, TenantPermission::StoresUpdate);
    }
}
