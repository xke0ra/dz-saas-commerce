<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\ProductImage;
use App\Models\User;

class ProductImagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductImagesView);
    }

    public function view(User $user, ProductImage $productImage): bool
    {
        return $user->hasTenantPermission($productImage->tenant_id, TenantPermission::ProductImagesView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductImagesCreate);
    }

    public function update(User $user, ProductImage $productImage): bool
    {
        return $user->hasTenantPermission($productImage->tenant_id, TenantPermission::ProductImagesUpdate);
    }

    public function delete(User $user, ProductImage $productImage): bool
    {
        return $user->hasTenantPermission($productImage->tenant_id, TenantPermission::ProductImagesDelete);
    }
}
