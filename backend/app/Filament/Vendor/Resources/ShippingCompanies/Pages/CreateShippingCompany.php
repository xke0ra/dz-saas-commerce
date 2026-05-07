<?php

namespace App\Filament\Vendor\Resources\ShippingCompanies\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\ShippingCompanies\ShippingCompanyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingCompany extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = ShippingCompanyResource::class;
}
