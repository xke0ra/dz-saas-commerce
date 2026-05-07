<?php

namespace App\Filament\Vendor\Resources\PaymentMethods\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethod extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = PaymentMethodResource::class;
}
