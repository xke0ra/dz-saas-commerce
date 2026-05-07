<?php

namespace App\Actions\Tenancy;

use App\Data\Tenancy\TenantInvitationResult;
use App\Enums\TenantInvitationStatus;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteTenantUser
{
    /**
     * @param  array<int, string>|array<string, bool>|null  $permissions
     */
    public function handle(
        Tenant $tenant,
        string $email,
        TenantRole $role,
        ?array $permissions,
        ?User $invitedBy = null,
        ?int $expiresInDays = null,
    ): TenantInvitationResult {
        $email = Str::lower(trim($email));
        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = now()->addDays($expiresInDays ?? 7);

        $invitation = DB::transaction(function () use ($tenant, $email, $role, $permissions, $invitedBy, $tokenHash, $expiresAt): TenantInvitation {
            TenantInvitation::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->where('status', TenantInvitationStatus::Pending)
                ->update([
                    'status' => TenantInvitationStatus::Revoked,
                    'revoked_at' => now(),
                ]);

            return TenantInvitation::query()
                ->withoutGlobalScope('current_tenant')
                ->create([
                    'tenant_id' => $tenant->id,
                    'invited_by_id' => $invitedBy?->id,
                    'email' => $email,
                    'role' => $role,
                    'permissions' => $permissions,
                    'token_hash' => $tokenHash,
                    'status' => TenantInvitationStatus::Pending,
                    'expires_at' => $expiresAt,
                ]);
        });

        Notification::route('mail', $email)
            ->notify(new TenantInvitationNotification($invitation, $plainToken));

        app(AuditLogger::class)->record(
            event: 'user.invited',
            auditable: $invitation,
            actor: $invitedBy,
            newValues: [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'permissions' => $invitation->permissions,
                'expires_at' => $invitation->expires_at?->toISOString(),
            ],
        );

        return new TenantInvitationResult($invitation->fresh(), $plainToken);
    }
}
