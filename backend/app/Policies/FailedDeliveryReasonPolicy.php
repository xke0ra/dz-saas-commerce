<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\FailedDeliveryReason;
use App\Models\User;

class FailedDeliveryReasonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::FailedDeliveryReasonsView);
    }

    public function view(User $user, FailedDeliveryReason $failedDeliveryReason): bool
    {
        return $user->hasTenantPermission($failedDeliveryReason->tenant_id, TenantPermission::FailedDeliveryReasonsView);
    }

    public function create(User $user): bool
    {
        return $user->hasCurrentTenantPermission(TenantPermission::FailedDeliveryReasonsManage);
    }

    public function update(User $user, FailedDeliveryReason $failedDeliveryReason): bool
    {
        return $user->hasTenantPermission($failedDeliveryReason->tenant_id, TenantPermission::FailedDeliveryReasonsManage);
    }

    public function delete(User $user, FailedDeliveryReason $failedDeliveryReason): bool
    {
        return $user->hasTenantPermission($failedDeliveryReason->tenant_id, TenantPermission::FailedDeliveryReasonsManage);
    }
}
