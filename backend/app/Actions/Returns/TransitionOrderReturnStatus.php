<?php

namespace App\Actions\Returns;

use App\Enums\OrderReturnStatus;
use App\Models\OrderReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionOrderReturnStatus
{
    /**
     * @var array<string, array<int, OrderReturnStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        OrderReturnStatus::Requested->value => [
            OrderReturnStatus::PendingReview,
            OrderReturnStatus::Approved,
            OrderReturnStatus::Rejected,
            OrderReturnStatus::Cancelled,
        ],
        OrderReturnStatus::PendingReview->value => [
            OrderReturnStatus::Approved,
            OrderReturnStatus::Rejected,
            OrderReturnStatus::Cancelled,
        ],
        OrderReturnStatus::Approved->value => [
            OrderReturnStatus::Received,
            OrderReturnStatus::Rejected,
            OrderReturnStatus::Cancelled,
        ],
        OrderReturnStatus::Received->value => [
            OrderReturnStatus::Refunded,
        ],
    ];

    public function handle(OrderReturn $orderReturn, OrderReturnStatus $targetStatus, ?string $resolutionNote = null): OrderReturn
    {
        return DB::transaction(function () use ($orderReturn, $targetStatus, $resolutionNote): OrderReturn {
            $lockedReturn = OrderReturn::query()
                ->whereKey($orderReturn->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $lockedReturn->status;

            if ($currentStatus !== $targetStatus && ! $this->canTransition($currentStatus, $targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Cannot transition return status from %s to %s.',
                        $currentStatus->getLabel(),
                        $targetStatus->getLabel(),
                    ),
                ]);
            }

            $attributes = [
                'status' => $targetStatus,
            ];

            if ($resolutionNote !== null) {
                $attributes['resolution_note'] = $resolutionNote;
            }

            if (in_array($targetStatus, [
                OrderReturnStatus::Rejected,
                OrderReturnStatus::Refunded,
                OrderReturnStatus::Cancelled,
            ], true)) {
                $attributes['resolved_at'] = now();
            }

            $lockedReturn->update($attributes);

            return $lockedReturn->refresh()->load(['order', 'customer']);
        });
    }

    public function canTransition(OrderReturnStatus $currentStatus, OrderReturnStatus $targetStatus): bool
    {
        if ($currentStatus === $targetStatus) {
            return true;
        }

        return in_array($targetStatus, $this->allowedTargets($currentStatus), true);
    }

    /**
     * @return array<int, OrderReturnStatus>
     */
    public function allowedTargets(OrderReturnStatus $currentStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStatus->value] ?? [];
    }
}
