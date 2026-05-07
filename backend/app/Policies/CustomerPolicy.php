<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::CustomersView);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasTenantPermission($customer->tenant_id, TenantPermission::CustomersView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::CustomersCreate);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasTenantPermission($customer->tenant_id, TenantPermission::CustomersUpdate);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasTenantPermission($customer->tenant_id, TenantPermission::CustomersDelete);
    }
}
