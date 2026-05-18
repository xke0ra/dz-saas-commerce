<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\ProductOption;
use App\Models\User;

class ProductOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductsView);
    }

    public function view(User $user, ProductOption $productOption): bool
    {
        return $user->hasTenantPermission($productOption->tenant_id, TenantPermission::ProductsView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductsCreate);
    }

    public function update(User $user, ProductOption $productOption): bool
    {
        return $user->hasTenantPermission($productOption->tenant_id, TenantPermission::ProductsUpdate);
    }

    public function delete(User $user, ProductOption $productOption): bool
    {
        return $user->hasTenantPermission($productOption->tenant_id, TenantPermission::ProductsDelete);
    }
}
