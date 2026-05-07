<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::CategoriesView);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->hasTenantPermission($category->tenant_id, TenantPermission::CategoriesView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::CategoriesCreate);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasTenantPermission($category->tenant_id, TenantPermission::CategoriesUpdate);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasTenantPermission($category->tenant_id, TenantPermission::CategoriesDelete);
    }
}
