<?php

namespace App\Filament\Vendor\Resources\Coupons\Pages;

use App\Filament\Vendor\Concerns\AssignsCurrentTenant;
use App\Filament\Vendor\Resources\Coupons\CouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    use AssignsCurrentTenant;

    protected static string $resource = CouponResource::class;
}
