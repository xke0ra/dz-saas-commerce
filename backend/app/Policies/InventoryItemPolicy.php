<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::InventoryView);
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasTenantPermission($inventoryItem->tenant_id, TenantPermission::InventoryView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::InventoryCreate);
    }

    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasTenantPermission($inventoryItem->tenant_id, TenantPermission::InventoryUpdate);
    }

    public function delete(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasTenantPermission($inventoryItem->tenant_id, TenantPermission::InventoryDelete);
    }
}
