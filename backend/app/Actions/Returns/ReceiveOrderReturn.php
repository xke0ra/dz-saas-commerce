<?php

namespace App\Actions\Returns;

use App\Actions\Inventory\RestockOrderReturn;
use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderReturnStatus;
use App\Enums\OrderStatus;
use App\Models\OrderReturn;
use Illuminate\Support\Facades\DB;

class ReceiveOrderReturn
{
    public function __construct(
        private readonly RestockOrderReturn $restockOrderReturn,
        private readonly TransitionOrderReturnStatus $transitionOrderReturnStatus,
        private readonly TransitionOrderStatus $transitionOrderStatus,
    ) {}

    public function handle(OrderReturn $orderReturn, bool $restock = true, ?string $resolutionNote = null): OrderReturn
    {
        return DB::transaction(function () use ($orderReturn, $restock, $resolutionNote): OrderReturn {
            $orderReturn = $this->transitionOrderReturnStatus->handle(
                $orderReturn,
                OrderReturnStatus::Received,
                $resolutionNote,
            );

            if ($restock) {
                $this->restockOrderReturn->handle($orderReturn);
            }

            $this->transitionOrderStatus->handle($orderReturn->order()->firstOrFail(), OrderStatus::Returned);

            return $orderReturn->refresh()->load(['order', 'customer']);
        });
    }
}
