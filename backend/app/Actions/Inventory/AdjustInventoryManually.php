<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Enums\TenantPermission;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustInventoryManually
{
    private const SENSITIVE_METADATA_KEYS = [
        'password',
        'token',
        'secret',
        'phone',
        'customer_phone',
        'idempotency_key',
        'payment_proof',
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        InventoryItem $inventoryItem,
        ?User $actor,
        int $quantityDelta,
        int $reservedDelta = 0,
        string $reason = '',
        array $metadata = [],
        StockMovementType $type = StockMovementType::ManualAdjustment,
    ): StockMovement {
        return DB::transaction(function () use ($inventoryItem, $actor, $quantityDelta, $reservedDelta, $reason, $metadata, $type): StockMovement {
            $lockedInventoryItem = InventoryItem::query()
                ->withoutGlobalScope('current_tenant')
                ->where('tenant_id', $inventoryItem->tenant_id)
                ->whereKey($inventoryItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureAdjustmentIsValid(
                inventoryItem: $lockedInventoryItem,
                actor: $actor,
                quantityDelta: $quantityDelta,
                reservedDelta: $reservedDelta,
                reason: $reason,
                type: $type,
            );

            $previousQuantity = $lockedInventoryItem->quantity;
            $previousReservedQuantity = $lockedInventoryItem->reserved_quantity;
            $newQuantity = $previousQuantity + $quantityDelta;
            $newReservedQuantity = $previousReservedQuantity + $reservedDelta;

            $this->ensureBalancesAreValid($lockedInventoryItem, $newQuantity, $newReservedQuantity);

            $lockedInventoryItem->update([
                'quantity' => $newQuantity,
                'reserved_quantity' => $newReservedQuantity,
            ]);

            $safeMetadata = $this->sanitizeMetadata($metadata);
            $movementMetadata = [
                'source' => 'manual_inventory_adjustment',
                'previous_quantity' => $previousQuantity,
                'previous_reserved_quantity' => $previousReservedQuantity,
                'new_quantity' => $newQuantity,
                'new_reserved_quantity' => $newReservedQuantity,
            ];

            if ($safeMetadata !== []) {
                $movementMetadata['context'] = $safeMetadata;
            }

            $stockMovement = StockMovement::query()
                ->withoutGlobalScope('current_tenant')
                ->create([
                    'tenant_id' => $lockedInventoryItem->tenant_id,
                    'product_id' => $lockedInventoryItem->product_id,
                    'inventory_item_id' => $lockedInventoryItem->id,
                    'order_id' => null,
                    'order_item_id' => null,
                    'order_return_id' => null,
                    'actor_id' => $actor?->id,
                    'type' => $type,
                    'quantity_delta' => $quantityDelta,
                    'reserved_delta' => $reservedDelta,
                    'balance_quantity_after' => $newQuantity,
                    'balance_reserved_after' => $newReservedQuantity,
                    'reason' => trim($reason),
                    'metadata' => $movementMetadata,
                ]);

            $auditMetadata = [
                'source' => 'manual_inventory_adjustment',
                'inventory_item_id' => $lockedInventoryItem->id,
                'product_id' => $lockedInventoryItem->product_id,
                'tenant_id' => $lockedInventoryItem->tenant_id,
                'stock_movement_id' => $stockMovement->id,
                'reason' => trim($reason),
                'quantity_delta' => $quantityDelta,
                'reserved_delta' => $reservedDelta,
            ];

            if ($safeMetadata !== []) {
                $auditMetadata['context'] = $safeMetadata;
            }

            $this->auditLogger->record(
                event: 'inventory_manual_adjustment',
                auditable: $lockedInventoryItem,
                tenantId: $lockedInventoryItem->tenant_id,
                actor: $actor,
                oldValues: [
                    'quantity' => $previousQuantity,
                    'reserved_quantity' => $previousReservedQuantity,
                ],
                newValues: [
                    'quantity' => $newQuantity,
                    'reserved_quantity' => $newReservedQuantity,
                ],
                metadata: $auditMetadata,
            );

            return $stockMovement->refresh();
        });
    }

    private function ensureAdjustmentIsValid(
        InventoryItem $inventoryItem,
        ?User $actor,
        int $quantityDelta,
        int $reservedDelta,
        string $reason,
        StockMovementType $type,
    ): void {
        if ($actor === null) {
            throw ValidationException::withMessages([
                'actor' => 'Manual inventory adjustments require an actor.',
            ]);
        }

        if (! $actor->hasTenantPermission($inventoryItem->tenant_id, TenantPermission::InventoryUpdate)) {
            throw ValidationException::withMessages([
                'actor' => 'The actor is not allowed to adjust this inventory item.',
            ]);
        }

        if ($quantityDelta === 0 && $reservedDelta === 0) {
            throw ValidationException::withMessages([
                'delta' => 'At least one inventory delta must be non-zero.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for manual inventory adjustments.',
            ]);
        }

        if (! in_array($type, [StockMovementType::ManualAdjustment, StockMovementType::Correction], true)) {
            throw ValidationException::withMessages([
                'type' => 'Manual inventory adjustments can only be recorded as manual adjustments or corrections.',
            ]);
        }
    }

    private function ensureBalancesAreValid(InventoryItem $inventoryItem, int $newQuantity, int $newReservedQuantity): void
    {
        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity_delta' => 'The adjustment would make inventory quantity negative.',
            ]);
        }

        if ($newReservedQuantity < 0) {
            throw ValidationException::withMessages([
                'reserved_delta' => 'The adjustment would make reserved inventory negative.',
            ]);
        }

        if (! $inventoryItem->allow_backorders && $newReservedQuantity > $newQuantity) {
            throw ValidationException::withMessages([
                'reserved_delta' => 'Reserved inventory cannot exceed quantity unless backorders are allowed.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $safe = [];

        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_METADATA_KEYS, true)) {
                continue;
            }

            $safe[$key] = is_array($value) ? $this->sanitizeMetadata($value) : $value;
        }

        return $safe;
    }
}
