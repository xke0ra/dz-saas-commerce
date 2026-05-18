<?php

namespace App\Filament\Vendor\Resources\ProductOptions\Pages;

use App\Filament\Vendor\Resources\ProductOptions\ProductOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductOptions extends ListRecords
{
    protected static string $resource = ProductOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
