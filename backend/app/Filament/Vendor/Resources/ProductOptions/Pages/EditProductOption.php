<?php

namespace App\Filament\Vendor\Resources\ProductOptions\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductOptions\ProductOptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductOption extends EditRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
