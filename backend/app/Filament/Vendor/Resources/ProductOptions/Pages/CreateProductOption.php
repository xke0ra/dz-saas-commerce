<?php

namespace App\Filament\Vendor\Resources\ProductOptions\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ProductOptions\ProductOptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductOption extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductOptionResource::class;
}
