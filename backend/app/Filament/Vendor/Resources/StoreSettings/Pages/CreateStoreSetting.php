<?php

namespace App\Filament\Vendor\Resources\StoreSettings\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\StoreSettings\StoreSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreSetting extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = StoreSettingResource::class;
}
