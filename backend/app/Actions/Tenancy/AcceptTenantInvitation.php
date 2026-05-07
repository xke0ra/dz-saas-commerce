<?php

namespace App\Actions\Tenancy;

use App\Enums\TenantInvitationStatus;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptTenantInvitation
{
    public function handle(string $plainToken, User $user): TenantInvitation
    {
        $tokenHash = hash('sha256', $plainToken);

        $result = DB::transaction(function () use ($tokenHash, $user): TenantInvitation|string {
            $invitation = TenantInvitation::query()
                ->withoutGlobalScope('current_tenant')
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw ValidationException::withMessages([
                    'token' => 'The invitation link is invalid.',
                ]);
            }

            if (! hash_equals(Str::lower($invitation->email), Str::lower($user->email))) {
                throw ValidationException::withMessages([
                    'email' => 'This invitation was sent to another email address.',
                ]);
            }

            if ($invitation->status === TenantInvitationStatus::Revoked || $invitation->revoked_at !== null) {
                throw ValidationException::withMessages([
                    'token' => 'The invitation has been revoked.',
                ]);
            }

            if ($invitation->accepted_at !== null || $invitation->status === TenantInvitationStatus::Accepted) {
                throw ValidationException::withMessages([
                    'token' => 'The invitation has already been accepted.',
                ]);
            }

            if ($invitation->expires_at->isPast()) {
                $invitation->update([
                    'status' => TenantInvitationStatus::Expired,
                ]);

                return 'expired';
            }

            $invitation->tenant->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => $invitation->role->value,
                    'permissions' => $invitation->permissions === null
                        ? null
                        : json_encode($invitation->permissions),
                ],
            ]);

            $invitation->update([
                'status' => TenantInvitationStatus::Accepted,
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
            ]);

            app(AuditLogger::class)->record(
                event: 'invitation.accepted',
                auditable: $invitation,
                actor: $user,
                newValues: [
                    'accepted_user_id' => $user->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                ],
            );

            return $invitation->refresh();
        });

        if ($result === 'expired') {
            throw ValidationException::withMessages([
                'token' => 'The invitation has expired.',
            ]);
        }

        return $result;
    }
}
