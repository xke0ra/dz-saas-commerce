<?php

namespace App\Filament\Vendor\Resources\ProductOptionValues\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductOptionValues\ProductOptionValueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductOptionValue extends EditRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductOptionValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
