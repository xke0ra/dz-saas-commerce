<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::BillingManage);
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->hasTenantPermission($subscription->tenant_id, TenantPermission::BillingManage);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return false;
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return false;
    }
}
