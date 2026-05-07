<?php

namespace App\Filament\Vendor\Resources\ProductImages\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductImages\ProductImageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductImage extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductImageResource::class;
}
