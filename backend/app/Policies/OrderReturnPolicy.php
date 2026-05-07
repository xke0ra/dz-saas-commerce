<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\OrderReturn;
use App\Models\User;

class OrderReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ReturnsView);
    }

    public function view(User $user, OrderReturn $orderReturn): bool
    {
        return $user->hasTenantPermission($orderReturn->tenant_id, TenantPermission::ReturnsView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ReturnsCreate);
    }

    public function update(User $user, OrderReturn $orderReturn): bool
    {
        return $user->hasTenantPermission($orderReturn->tenant_id, TenantPermission::ReturnsUpdate);
    }

    public function approve(User $user, OrderReturn $orderReturn): bool
    {
        return $user->hasTenantPermission($orderReturn->tenant_id, TenantPermission::ReturnsUpdate);
    }

    public function reject(User $user, OrderReturn $orderReturn): bool
    {
        return $user->hasTenantPermission($orderReturn->tenant_id, TenantPermission::ReturnsUpdate);
    }

    public function receive(User $user, OrderReturn $orderReturn): bool
    {
        return $user->hasTenantPermission($orderReturn->tenant_id, TenantPermission::ReturnsUpdate);
    }

    public function refund(User $user, OrderReturn $orderReturn): bool
    {
        return $user->hasTenantPermission($orderReturn->tenant_id, TenantPermission::ReturnsUpdate);
    }

    public function cancel(User $user, OrderReturn $orderReturn): bool
    {
        return $user->hasTenantPermission($orderReturn->tenant_id, TenantPermission::ReturnsUpdate);
    }

    public function delete(User $user, OrderReturn $orderReturn): bool
    {
        return false;
    }
}
