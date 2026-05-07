<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\ThemeSetting;
use App\Models\User;

class ThemeSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::StoresView);
    }

    public function view(User $user, ThemeSetting $themeSetting): bool
    {
        return $user->hasTenantPermission($themeSetting->tenant_id, TenantPermission::StoresView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::StoresUpdate);
    }

    public function update(User $user, ThemeSetting $themeSetting): bool
    {
        return $user->hasTenantPermission($themeSetting->tenant_id, TenantPermission::StoresUpdate);
    }

    public function delete(User $user, ThemeSetting $themeSetting): bool
    {
        return $user->hasTenantPermission($themeSetting->tenant_id, TenantPermission::StoresUpdate);
    }
}
