<?php

namespace App\Filament\Vendor\Resources\ProductImages\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductImages\ProductImageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductImage extends EditRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
