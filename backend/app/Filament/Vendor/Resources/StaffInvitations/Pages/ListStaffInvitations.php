<?php

namespace App\Filament\Vendor\Resources\StaffInvitations\Pages;

use App\Filament\Vendor\Resources\StaffInvitations\StaffInvitationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaffInvitations extends ListRecords
{
    protected static string $resource = StaffInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
