<?php

namespace App\Filament\Vendor\Resources\ProductVariantOptionValues\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductVariantOptionValues\ProductVariantOptionValueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductVariantOptionValue extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductVariantOptionValueResource::class;
}
