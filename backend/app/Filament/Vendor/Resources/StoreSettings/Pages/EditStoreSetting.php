<?php

namespace App\Filament\Vendor\Resources\StoreSettings\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\StoreSettings\StoreSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStoreSetting extends EditRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = StoreSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
