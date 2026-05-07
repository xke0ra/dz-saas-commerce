<?php

namespace App\Filament\Vendor\Resources\Products\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ProductResource::class;
}
