<?php

use App\Enums\PlatformRole;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;

it('allows only super admins to view audit logs', function (): void {
    $admin = User::factory()->create([
        'platform_role' => PlatformRole::SuperAdmin,
    ]);
    $vendor = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $auditLog = AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'event' => 'tenant.created',
        'auditable_type' => $tenant->getMorphClass(),
        'auditable_id' => $tenant->id,
        'metadata' => [],
    ]);

    expect($admin->can('viewAny', AuditLog::class))->toBeTrue()
        ->and($admin->can('view', $auditLog))->toBeTrue()
        ->and($vendor->can('viewAny', AuditLog::class))->toBeFalse()
        ->and($vendor->can('view', $auditLog))->toBeFalse();
});

it('keeps audit logs immutable even for super admins', function (): void {
    $admin = User::factory()->create([
        'platform_role' => PlatformRole::SuperAdmin,
    ]);
    $tenant = Tenant::factory()->create();
    $auditLog = AuditLog::query()->create([
        'tenant_id' => $tenant->id,
        'event' => 'tenant.suspended',
        'auditable_type' => $tenant->getMorphClass(),
        'auditable_id' => $tenant->id,
        'old_values' => ['status' => 'active'],
        'new_values' => ['status' => 'suspended'],
    ]);

    expect($admin->can('create', AuditLog::class))->toBeFalse()
        ->and($admin->can('update', $auditLog))->toBeFalse()
        ->and($admin->can('delete', $auditLog))->toBeFalse()
        ->and($admin->can('forceDelete', $auditLog))->toBeFalse()
        ->and($admin->can('restore', $auditLog))->toBeFalse()
        ->and($admin->can('replicate', $auditLog))->toBeFalse()
        ->and($admin->can('reorder', AuditLog::class))->toBeFalse()
        ->and(AuditLogResource::canCreate())->toBeFalse()
        ->and(AuditLogResource::canEdit($auditLog))->toBeFalse()
        ->and(AuditLogResource::canDelete($auditLog))->toBeFalse()
        ->and(AuditLogResource::canDeleteAny())->toBeFalse()
        ->and(AuditLogResource::canForceDelete($auditLog))->toBeFalse()
        ->and(AuditLogResource::canForceDeleteAny())->toBeFalse()
        ->and(AuditLogResource::canRestore($auditLog))->toBeFalse()
        ->and(AuditLogResource::canRestoreAny())->toBeFalse()
        ->and(AuditLogResource::canReplicate($auditLog))->toBeFalse()
        ->and(AuditLogResource::canReorder())->toBeFalse();
});
