<?php

namespace App\Filament\Vendor\Resources\StaffInvitations\Pages;

use App\Actions\Tenancy\InviteTenantUser;
use App\Enums\TenantRole;
use App\Filament\Vendor\Resources\StaffInvitations\StaffInvitationResource;
use App\Support\Tenancy\CurrentTenant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaffInvitation extends CreateRecord
{
    protected static string $resource = StaffInvitationResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tenant = app(CurrentTenant::class)->get();

        abort_if($tenant === null, 403);

        return app(InviteTenantUser::class)->handle(
            tenant: $tenant,
            email: $data['email'],
            role: TenantRole::from($data['role']),
            permissions: $data['permissions'] ?? null,
            invitedBy: auth()->user(),
        )->invitation;
    }
}
