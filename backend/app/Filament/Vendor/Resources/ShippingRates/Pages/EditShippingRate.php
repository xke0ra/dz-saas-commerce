<?php

namespace App\Filament\Vendor\Resources\ShippingRates\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ShippingRates\ShippingRateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingRate extends EditRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ShippingRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
