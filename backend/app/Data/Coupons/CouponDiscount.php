<?php

namespace App\Data\Coupons;

use App\Models\Coupon;

class CouponDiscount
{
    public function __construct(
        public readonly Coupon $coupon,
        public readonly int $discountMinor,
    ) {}
}
