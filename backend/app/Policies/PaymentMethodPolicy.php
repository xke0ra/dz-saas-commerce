<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::PaymentMethodsView);
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasTenantPermission($paymentMethod->tenant_id, TenantPermission::PaymentMethodsView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::PaymentMethodsManage);
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasTenantPermission($paymentMethod->tenant_id, TenantPermission::PaymentMethodsManage);
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasTenantPermission($paymentMethod->tenant_id, TenantPermission::PaymentMethodsManage);
    }
}
