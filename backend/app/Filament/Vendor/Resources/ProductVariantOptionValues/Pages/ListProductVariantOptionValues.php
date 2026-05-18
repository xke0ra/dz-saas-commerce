<?php

namespace App\Filament\Vendor\Resources\ProductVariantOptionValues\Pages;

use App\Filament\Vendor\Resources\ProductVariantOptionValues\ProductVariantOptionValueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductVariantOptionValues extends ListRecords
{
    protected static string $resource = ProductVariantOptionValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
