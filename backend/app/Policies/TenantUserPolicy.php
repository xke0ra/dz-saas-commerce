<?php

namespace App\Policies;

use App\Enums\PlanFeatureKey;
use App\Enums\TenantPermission;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Billing\SubscriptionFeatureGate;
use App\Support\Tenancy\CurrentTenant;

class TenantUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::StaffManage);
    }

    public function view(User $user, TenantUser $tenantUser): bool
    {
        return $user->hasTenantPermission($tenantUser->tenant_id, TenantPermission::StaffManage);
    }

    public function create(User $user): bool
    {
        if (! $user->hasCurrentTenantPermission(TenantPermission::StaffManage)) {
            return false;
        }

        $tenant = app(CurrentTenant::class)->get();

        return $tenant !== null
            && app(SubscriptionFeatureGate::class)->withinLimit($tenant, PlanFeatureKey::MaxStaffUsers);
    }

    public function update(User $user, TenantUser $tenantUser): bool
    {
        return $user->hasTenantPermission($tenantUser->tenant_id, TenantPermission::StaffManage);
    }

    public function delete(User $user, TenantUser $tenantUser): bool
    {
        return $user->hasTenantPermission($tenantUser->tenant_id, TenantPermission::StaffManage)
            && $user->getKey() !== $tenantUser->user_id;
    }
}
