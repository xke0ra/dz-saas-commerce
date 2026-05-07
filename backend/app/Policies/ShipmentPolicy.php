<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ShipmentsView);
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->hasTenantPermission($shipment->tenant_id, TenantPermission::ShipmentsView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ShipmentsCreate);
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->hasTenantPermission($shipment->tenant_id, TenantPermission::ShipmentsUpdate);
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return false;
    }
}
