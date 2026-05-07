<?php

namespace App\Filament\Vendor\Resources\StaffMembers\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\StaffMembers\StaffMemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaffMember extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = StaffMemberResource::class;
}
