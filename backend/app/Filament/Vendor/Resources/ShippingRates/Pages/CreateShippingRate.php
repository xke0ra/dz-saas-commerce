<?php

namespace App\Filament\Vendor\Resources\ShippingRates\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ShippingRates\ShippingRateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingRate extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ShippingRateResource::class;
}
