<?php

namespace App\Actions\Returns;

use App\Actions\Orders\TransitionOrderStatus;
use App\Actions\Payments\RefundOrderPayment;
use App\Enums\OrderReturnStatus;
use App\Enums\OrderStatus;
use App\Models\OrderReturn;
use Illuminate\Support\Facades\DB;

class RefundOrderReturn
{
    public function __construct(
        private readonly RefundOrderPayment $refundOrderPayment,
        private readonly TransitionOrderReturnStatus $transitionOrderReturnStatus,
        private readonly TransitionOrderStatus $transitionOrderStatus,
    ) {}

    public function handle(OrderReturn $orderReturn, ?string $resolutionNote = null): OrderReturn
    {
        return DB::transaction(function () use ($orderReturn, $resolutionNote): OrderReturn {
            $orderReturn = OrderReturn::query()
                ->whereKey($orderReturn->getKey())
                ->with('order')
                ->lockForUpdate()
                ->firstOrFail();

            $this->refundOrderPayment->handle($orderReturn->order, $resolutionNote);

            $orderReturn = $this->transitionOrderReturnStatus->handle(
                $orderReturn,
                OrderReturnStatus::Refunded,
                $resolutionNote,
            );

            $this->transitionOrderStatus->handle($orderReturn->order()->firstOrFail(), OrderStatus::Refunded);

            return $orderReturn->refresh()->load(['order', 'customer']);
        });
    }
}
