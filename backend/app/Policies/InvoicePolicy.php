<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::BillingManage);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasTenantPermission($invoice->tenant_id, TenantPermission::BillingManage);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
