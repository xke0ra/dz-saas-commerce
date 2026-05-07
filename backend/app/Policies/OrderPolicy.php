<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::OrdersView);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasTenantPermission($order->tenant_id, TenantPermission::OrdersView);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->hasTenantPermission($order->tenant_id, TenantPermission::OrdersUpdate);
    }

    public function confirm(User $user, Order $order): bool
    {
        return $user->hasTenantPermission($order->tenant_id, TenantPermission::OrdersConfirm);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->hasTenantPermission($order->tenant_id, TenantPermission::OrdersCancel);
    }

    public function ship(User $user, Order $order): bool
    {
        return $user->hasTenantPermission($order->tenant_id, TenantPermission::OrdersShip);
    }

    public function collectPayment(User $user, Order $order): bool
    {
        return $user->hasTenantPermission($order->tenant_id, TenantPermission::PaymentsManage);
    }

    public function failPayment(User $user, Order $order): bool
    {
        return $user->hasTenantPermission($order->tenant_id, TenantPermission::PaymentsManage);
    }

    public function refundPayment(User $user, Order $order): bool
    {
        return $user->hasTenantPermission($order->tenant_id, TenantPermission::PaymentsManage);
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }
}
