<?php

namespace App\Filament\Vendor\Resources\ProductOptionValues\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductOptionValues\ProductOptionValueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductOptionValue extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductOptionValueResource::class;
}
