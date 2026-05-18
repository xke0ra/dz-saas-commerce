<?php

namespace App\Filament\Vendor\Resources\ProductVariants\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductVariants\ProductVariantResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductVariant extends EditRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
