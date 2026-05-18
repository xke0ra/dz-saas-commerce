<?php

namespace App\Filament\Vendor\Resources\ProductOptionValues\Pages;

use App\Filament\Vendor\Resources\ProductOptionValues\ProductOptionValueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductOptionValues extends ListRecords
{
    protected static string $resource = ProductOptionValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
