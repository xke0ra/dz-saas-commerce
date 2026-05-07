<?php

namespace App\Filament\Vendor\Resources\Categories\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = CategoryResource::class;
}
