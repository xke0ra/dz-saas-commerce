<?php

namespace App\Data\Checkout;

use App\Models\Order;

class CheckoutOrderResult
{
    public function __construct(
        public readonly Order $order,
        public readonly int $statusCode,
    ) {}
}
