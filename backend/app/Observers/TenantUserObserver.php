<?php

namespace App\Observers;

use App\Models\TenantUser;
use App\Support\Audit\AuditLogger;

class TenantUserObserver
{
    public function created(TenantUser $tenantUser): void
    {
        app(AuditLogger::class)->record(
            event: 'staff.added',
            auditable: $tenantUser,
            newValues: [
                'user_id' => $tenantUser->user_id,
                'role' => $tenantUser->role,
                'permissions' => $tenantUser->permissions,
            ],
        );
    }

    public function updated(TenantUser $tenantUser): void
    {
        if ($tenantUser->wasChanged('role')) {
            app(AuditLogger::class)->record(
                event: 'staff.role_changed',
                auditable: $tenantUser,
                oldValues: [
                    'role' => $tenantUser->getRawOriginal('role'),
                ],
                newValues: [
                    'role' => $tenantUser->role,
                ],
            );
        }

        if ($tenantUser->wasChanged('permissions')) {
            app(AuditLogger::class)->record(
                event: 'staff.permission_changed',
                auditable: $tenantUser,
                oldValues: [
                    'permissions' => $tenantUser->getRawOriginal('permissions'),
                ],
                newValues: [
                    'permissions' => $tenantUser->permissions,
                ],
            );
        }
    }

    public function deleted(TenantUser $tenantUser): void
    {
        app(AuditLogger::class)->record(
            event: 'staff.removed',
            auditable: $tenantUser,
            oldValues: [
                'user_id' => $tenantUser->user_id,
                'role' => $tenantUser->role,
                'permissions' => $tenantUser->permissions,
            ],
        );
    }
}
