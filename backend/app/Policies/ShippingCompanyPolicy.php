<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\ShippingCompany;
use App\Models\User;

class ShippingCompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ShippingCompaniesView);
    }

    public function view(User $user, ShippingCompany $shippingCompany): bool
    {
        return $user->hasTenantPermission($shippingCompany->tenant_id, TenantPermission::ShippingCompaniesView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::ShippingCompaniesManage);
    }

    public function update(User $user, ShippingCompany $shippingCompany): bool
    {
        return $user->hasTenantPermission($shippingCompany->tenant_id, TenantPermission::ShippingCompaniesManage);
    }

    public function delete(User $user, ShippingCompany $shippingCompany): bool
    {
        return $user->hasTenantPermission($shippingCompany->tenant_id, TenantPermission::ShippingCompaniesManage);
    }
}
