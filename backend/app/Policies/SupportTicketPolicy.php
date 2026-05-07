<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isPlatformSupport()
            || $user->hasCurrentTenantPermission(TenantPermission::SupportTicketsView);
    }

    public function view(User $user, SupportTicket $supportTicket): bool
    {
        return $user->isSuperAdmin()
            || $user->isPlatformSupport()
            || $user->hasTenantPermission($supportTicket->tenant_id, TenantPermission::SupportTicketsView);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isPlatformSupport()
            || $user->hasCurrentTenantPermission(TenantPermission::SupportTicketsCreate);
    }

    public function update(User $user, SupportTicket $supportTicket): bool
    {
        return $user->isSuperAdmin()
            || $user->isPlatformSupport()
            || $user->hasTenantPermission($supportTicket->tenant_id, TenantPermission::SupportTicketsUpdate);
    }

    public function delete(User $user, SupportTicket $supportTicket): bool
    {
        return $user->isSuperAdmin();
    }
}
