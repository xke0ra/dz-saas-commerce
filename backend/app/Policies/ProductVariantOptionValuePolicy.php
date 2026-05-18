<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\ProductVariantOptionValue;
use App\Models\User;

class ProductVariantOptionValuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductsView);
    }

    public function view(User $user, ProductVariantOptionValue $productVariantOptionValue): bool
    {
        return $user->hasTenantPermission($productVariantOptionValue->tenant_id, TenantPermission::ProductsView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductsCreate);
    }

    public function update(User $user, ProductVariantOptionValue $productVariantOptionValue): bool
    {
        return $user->hasTenantPermission($productVariantOptionValue->tenant_id, TenantPermission::ProductsUpdate);
    }

    public function delete(User $user, ProductVariantOptionValue $productVariantOptionValue): bool
    {
        return $user->hasTenantPermission($productVariantOptionValue->tenant_id, TenantPermission::ProductsDelete);
    }
}
