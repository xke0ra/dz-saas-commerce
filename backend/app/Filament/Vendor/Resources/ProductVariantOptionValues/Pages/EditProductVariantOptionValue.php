<?php

namespace App\Filament\Vendor\Resources\ProductVariantOptionValues\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductVariantOptionValues\ProductVariantOptionValueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductVariantOptionValue extends EditRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductVariantOptionValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
