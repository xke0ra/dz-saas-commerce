<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\SubscriptionPayment;
use App\Models\User;

class SubscriptionPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::BillingManage);
    }

    public function view(User $user, SubscriptionPayment $subscriptionPayment): bool
    {
        return $user->hasTenantPermission($subscriptionPayment->tenant_id, TenantPermission::BillingManage);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::BillingManage);
    }

    public function update(User $user, SubscriptionPayment $subscriptionPayment): bool
    {
        return false;
    }

    public function delete(User $user, SubscriptionPayment $subscriptionPayment): bool
    {
        return false;
    }

    public function confirm(User $user, SubscriptionPayment $subscriptionPayment): bool
    {
        return false;
    }

    public function reject(User $user, SubscriptionPayment $subscriptionPayment): bool
    {
        return false;
    }
}
