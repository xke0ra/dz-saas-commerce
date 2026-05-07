<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $user->hasTenantPermission($payment->tenant_id, TenantPermission::OrdersView);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->hasTenantPermission($payment->tenant_id, TenantPermission::PaymentsManage);
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->hasTenantPermission($payment->tenant_id, TenantPermission::PaymentsManage);
    }
}
