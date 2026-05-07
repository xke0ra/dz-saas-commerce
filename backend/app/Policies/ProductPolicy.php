<?php

namespace App\Policies;

use App\Enums\PlanFeatureKey;
use App\Enums\TenantPermission;
use App\Models\Product;
use App\Models\User;
use App\Support\Billing\SubscriptionFeatureGate;
use App\Support\Tenancy\CurrentTenant;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ProductsView);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasTenantPermission($product->tenant_id, TenantPermission::ProductsView);
    }

    public function create(User $user): bool
    {
        if (! $user->hasCurrentTenantPermission(TenantPermission::ProductsCreate)) {
            return false;
        }

        $tenant = app(CurrentTenant::class)->get();

        return $tenant !== null
            && app(SubscriptionFeatureGate::class)->withinLimit($tenant, PlanFeatureKey::MaxProducts);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasTenantPermission($product->tenant_id, TenantPermission::ProductsUpdate);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasTenantPermission($product->tenant_id, TenantPermission::ProductsDelete);
    }
}
