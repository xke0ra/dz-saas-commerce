<?php

namespace App\Filament\Vendor\Resources\Shipments\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\Shipments\ShipmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShipment extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ShipmentResource::class;
}
