<?php

namespace App\Filament\Vendor\Resources\ShippingCompanies\Pages;

use App\Filament\Vendor\Resources\ShippingCompanies\ShippingCompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingCompanies extends ListRecords
{
    protected static string $resource = ShippingCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
