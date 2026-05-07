<?php

namespace App\Filament\Vendor\Resources\OrderReturns\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\OrderReturns\OrderReturnResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrderReturn extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = OrderReturnResource::class;
}
