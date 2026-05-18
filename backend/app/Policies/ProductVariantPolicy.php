<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\ProductVariant;
use App\Models\User;

class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductsView);
    }

    public function view(User $user, ProductVariant $productVariant): bool
    {
        return $user->hasTenantPermission($productVariant->tenant_id, TenantPermission::ProductsView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductsCreate);
    }

    public function update(User $user, ProductVariant $productVariant): bool
    {
        return $user->hasTenantPermission($productVariant->tenant_id, TenantPermission::ProductsUpdate);
    }

    public function delete(User $user, ProductVariant $productVariant): bool
    {
        return $user->hasTenantPermission($productVariant->tenant_id, TenantPermission::ProductsDelete);
    }
}
