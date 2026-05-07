<?php

namespace App\Filament\Vendor\Resources\StoreSettings\Pages;

use App\Filament\Vendor\Resources\StoreSettings\StoreSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreSettings extends ListRecords
{
    protected static string $resource = StoreSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
