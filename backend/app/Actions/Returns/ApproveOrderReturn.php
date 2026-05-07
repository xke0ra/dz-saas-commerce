<?php

namespace App\Actions\Returns;

use App\Enums\OrderReturnStatus;
use App\Models\OrderReturn;

class ApproveOrderReturn
{
    public function __construct(
        private readonly TransitionOrderReturnStatus $transitionOrderReturnStatus,
    ) {}

    public function handle(OrderReturn $orderReturn, ?string $resolutionNote = null): OrderReturn
    {
        return $this->transitionOrderReturnStatus->handle($orderReturn, OrderReturnStatus::Approved, $resolutionNote);
    }
}
