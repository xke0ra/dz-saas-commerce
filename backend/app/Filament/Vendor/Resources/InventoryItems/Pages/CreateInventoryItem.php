<?php

namespace App\Filament\Vendor\Resources\InventoryItems\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\InventoryItems\InventoryItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryItem extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = InventoryItemResource::class;
}
