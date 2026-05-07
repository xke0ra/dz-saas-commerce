<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::CouponsView);
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $user->hasTenantPermission($coupon->tenant_id, TenantPermission::CouponsView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::CouponsManage);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->hasTenantPermission($coupon->tenant_id, TenantPermission::CouponsManage);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $user->hasTenantPermission($coupon->tenant_id, TenantPermission::CouponsManage);
    }
}
