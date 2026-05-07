<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;

class PackOrder
{
    public function __construct(
        private readonly TransitionOrderStatus $transitionOrderStatus,
    ) {}

    public function handle(Order $order): Order
    {
        return $this->transitionOrderStatus->handle($order, OrderStatus::Packed);
    }
}
