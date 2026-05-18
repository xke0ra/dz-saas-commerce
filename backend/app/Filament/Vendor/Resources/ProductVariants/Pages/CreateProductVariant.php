<?php

namespace App\Filament\Vendor\Resources\ProductVariants\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductVariants\ProductVariantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductVariant extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductVariantResource::class;
}
