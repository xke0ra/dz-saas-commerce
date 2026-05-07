<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\ShippingRate;
use App\Models\User;

class ShippingRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ShippingRatesView);
    }

    public function view(User $user, ShippingRate $shippingRate): bool
    {
        return $user->hasTenantPermission($shippingRate->tenant_id, TenantPermission::ShippingRatesView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ShippingRatesManage);
    }

    public function update(User $user, ShippingRate $shippingRate): bool
    {
        return $user->hasTenantPermission($shippingRate->tenant_id, TenantPermission::ShippingRatesManage);
    }

    public function delete(User $user, ShippingRate $shippingRate): bool
    {
        return $user->hasTenantPermission($shippingRate->tenant_id, TenantPermission::ShippingRatesManage);
    }
}
