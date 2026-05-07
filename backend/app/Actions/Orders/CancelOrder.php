<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;

class CancelOrder
{
    public function __construct(
        private readonly TransitionOrderStatus $transitionOrderStatus,
    ) {}

    public function handle(Order $order): Order
    {
        return $this->transitionOrderStatus->handle($order, OrderStatus::Cancelled);
    }
}
