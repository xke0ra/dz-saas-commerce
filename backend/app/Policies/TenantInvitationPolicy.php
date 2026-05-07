<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\TenantInvitation;
use App\Models\User;

class TenantInvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::StaffManage);
    }

    public function view(User $user, TenantInvitation $tenantInvitation): bool
    {
        return $user->hasTenantPermission($tenantInvitation->tenant_id, TenantPermission::StaffManage);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::StaffManage);
    }

    public function update(User $user, TenantInvitation $tenantInvitation): bool
    {
        return $user->hasTenantPermission($tenantInvitation->tenant_id, TenantPermission::StaffManage);
    }

    public function delete(User $user, TenantInvitation $tenantInvitation): bool
    {
        return $user->hasTenantPermission($tenantInvitation->tenant_id, TenantPermission::StaffManage);
    }
}
