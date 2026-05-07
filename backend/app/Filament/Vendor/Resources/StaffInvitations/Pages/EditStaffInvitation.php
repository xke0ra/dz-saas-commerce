<?php

namespace App\Filament\Vendor\Resources\StaffInvitations\Pages;

use App\Filament\Vendor\Resources\StaffInvitations\StaffInvitationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaffInvitation extends EditRecord
{
    protected static string $resource = StaffInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
