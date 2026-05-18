<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\ProductOptionValue;
use App\Models\User;

class ProductOptionValuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductsView);
    }

    public function view(User $user, ProductOptionValue $productOptionValue): bool
    {
        return $user->hasTenantPermission($productOptionValue->tenant_id, TenantPermission::ProductsView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductsCreate);
    }

    public function update(User $user, ProductOptionValue $productOptionValue): bool
    {
        return $user->hasTenantPermission($productOptionValue->tenant_id, TenantPermission::ProductsUpdate);
    }

    public function delete(User $user, ProductOptionValue $productOptionValue): bool
    {
        return $user->hasTenantPermission($productOptionValue->tenant_id, TenantPermission::ProductsDelete);
    }
}
