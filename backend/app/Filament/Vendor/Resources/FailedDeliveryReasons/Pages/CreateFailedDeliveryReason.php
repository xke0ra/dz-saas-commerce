<?php

namespace App\Filament\Vendor\Resources\FailedDeliveryReasons\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\FailedDeliveryReasons\FailedDeliveryReasonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFailedDeliveryReason extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = FailedDeliveryReasonResource::class;
}
