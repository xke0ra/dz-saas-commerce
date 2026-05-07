<?php

namespace App\Filament\Vendor\Resources\ThemeSettings\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ThemeSettings\ThemeSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateThemeSetting extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ThemeSettingResource::class;
}
