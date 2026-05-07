<?php

namespace App\Observers;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Support\Audit\AuditLogger;

class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        app(AuditLogger::class)->record(
            event: 'tenant.created',
            auditable: $tenant,
            newValues: [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'owner_id' => $tenant->owner_id,
            ],
        );
    }

    public function saved(Tenant $tenant): void
    {
        if ($tenant->owner_id === null) {
            return;
        }

        $tenant->users()->syncWithoutDetaching([
            $tenant->owner_id => [
                'role' => TenantRole::Owner->value,
                'permissions' => null,
            ],
        ]);
    }

    public function updated(Tenant $tenant): void
    {
        if (! $tenant->wasChanged('status')) {
            return;
        }

        app(AuditLogger::class)->record(
            event: $tenant->status->value === 'suspended' ? 'tenant.suspended' : 'tenant.status_changed',
            auditable: $tenant,
            oldValues: [
                'status' => $tenant->getRawOriginal('status'),
            ],
            newValues: [
                'status' => $tenant->status,
            ],
        );
    }
}
