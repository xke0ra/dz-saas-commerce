<?php

use App\Actions\Inventory\AdjustInventoryManually;
use App\Enums\StockMovementType;
use App\Enums\TenantRole;
use App\Models\AuditLog;
use App\Models\CheckoutIdempotencyRecord;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('increases inventory quantity and records stock movement and audit log', function (): void {
    $inventoryItem = createManualAdjustmentInventoryItem([
        'quantity' => 10,
        'reserved_quantity' => 2,
    ]);
    $actor = createManualAdjustmentActor($inventoryItem->tenant);

    $movement = app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 5,
        reason: 'Cycle count increase.',
        metadata: ['batch' => 'COUNT-001'],
    );

    $inventoryItem->refresh();
    $auditLog = AuditLog::query()
        ->where('event', 'inventory_manual_adjustment')
        ->where('auditable_id', $inventoryItem->id)
        ->firstOrFail();

    expect($inventoryItem->quantity)->toBe(15)
        ->and($inventoryItem->reserved_quantity)->toBe(2)
        ->and($movement->tenant_id)->toBe($inventoryItem->tenant_id)
        ->and($movement->product_id)->toBe($inventoryItem->product_id)
        ->and($movement->inventory_item_id)->toBe($inventoryItem->id)
        ->and($movement->actor_id)->toBe($actor->id)
        ->and($movement->type)->toBe(StockMovementType::ManualAdjustment)
        ->and($movement->quantity_delta)->toBe(5)
        ->and($movement->reserved_delta)->toBe(0)
        ->and($movement->balance_quantity_after)->toBe(15)
        ->and($movement->balance_reserved_after)->toBe(2)
        ->and($movement->reason)->toBe('Cycle count increase.')
        ->and($movement->metadata)->toMatchArray([
            'source' => 'manual_inventory_adjustment',
            'previous_quantity' => 10,
            'previous_reserved_quantity' => 2,
            'new_quantity' => 15,
            'new_reserved_quantity' => 2,
            'context' => ['batch' => 'COUNT-001'],
        ])
        ->and($auditLog->tenant_id)->toBe($inventoryItem->tenant_id)
        ->and($auditLog->actor_id)->toBe($actor->id)
        ->and($auditLog->auditable_type)->toBe($inventoryItem->getMorphClass())
        ->and($auditLog->auditable_id)->toBe($inventoryItem->id)
        ->and($auditLog->old_values)->toMatchArray([
            'quantity' => 10,
            'reserved_quantity' => 2,
        ])
        ->and($auditLog->new_values)->toMatchArray([
            'quantity' => 15,
            'reserved_quantity' => 2,
        ])
        ->and($auditLog->metadata)->toMatchArray([
            'source' => 'manual_inventory_adjustment',
            'inventory_item_id' => $inventoryItem->id,
            'product_id' => $inventoryItem->product_id,
            'tenant_id' => $inventoryItem->tenant_id,
            'stock_movement_id' => $movement->id,
            'reason' => 'Cycle count increase.',
            'quantity_delta' => 5,
            'reserved_delta' => 0,
            'context' => ['batch' => 'COUNT-001'],
        ]);
});

it('decreases inventory quantity and records stock movement and audit log', function (): void {
    $inventoryItem = createManualAdjustmentInventoryItem([
        'quantity' => 10,
        'reserved_quantity' => 2,
    ]);
    $actor = createManualAdjustmentActor($inventoryItem->tenant);

    $movement = app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: -3,
        reason: 'Damaged stock removal.',
    );

    $auditLog = AuditLog::query()
        ->where('event', 'inventory_manual_adjustment')
        ->where('auditable_id', $inventoryItem->id)
        ->firstOrFail();

    expect($inventoryItem->refresh()->quantity)->toBe(7)
        ->and($inventoryItem->reserved_quantity)->toBe(2)
        ->and($movement->quantity_delta)->toBe(-3)
        ->and($movement->reserved_delta)->toBe(0)
        ->and($movement->balance_quantity_after)->toBe(7)
        ->and($movement->balance_reserved_after)->toBe(2)
        ->and($auditLog->old_values)->toMatchArray(['quantity' => 10, 'reserved_quantity' => 2])
        ->and($auditLog->new_values)->toMatchArray(['quantity' => 7, 'reserved_quantity' => 2]);
});

it('adjusts reserved quantity and records reserved delta', function (): void {
    $inventoryItem = createManualAdjustmentInventoryItem([
        'quantity' => 10,
        'reserved_quantity' => 2,
    ]);
    $actor = createManualAdjustmentActor($inventoryItem->tenant);

    $movement = app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 0,
        reservedDelta: 3,
        reason: 'Reservation reconciliation.',
    );

    expect($inventoryItem->refresh()->quantity)->toBe(10)
        ->and($inventoryItem->reserved_quantity)->toBe(5)
        ->and($movement->quantity_delta)->toBe(0)
        ->and($movement->reserved_delta)->toBe(3)
        ->and($movement->balance_quantity_after)->toBe(10)
        ->and($movement->balance_reserved_after)->toBe(5);
});

it('records correction stock movement type when requested', function (): void {
    $inventoryItem = createManualAdjustmentInventoryItem();
    $actor = createManualAdjustmentActor($inventoryItem->tenant);

    $movement = app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 1,
        reason: 'Correct previous count.',
        type: StockMovementType::Correction,
    );

    expect($movement->type)->toBe(StockMovementType::Correction);
});

it('rejects zero deltas', function (): void {
    [$inventoryItem, $actor] = createManualAdjustmentInventoryItemAndActor();

    expect(fn () => app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 0,
        reservedDelta: 0,
        reason: 'No change.',
    ))->toThrow(ValidationException::class);

    expect(StockMovement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'inventory_manual_adjustment')->count())->toBe(0);
});

it('rejects blank reason', function (): void {
    [$inventoryItem, $actor] = createManualAdjustmentInventoryItemAndActor();

    expect(fn () => app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 1,
        reason: '   ',
    ))->toThrow(ValidationException::class);

    expect(StockMovement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'inventory_manual_adjustment')->count())->toBe(0);
});

it('rejects missing actor', function (): void {
    $inventoryItem = createManualAdjustmentInventoryItem();

    expect(fn () => app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: null,
        quantityDelta: 1,
        reason: 'Missing actor.',
    ))->toThrow(ValidationException::class);

    expect(StockMovement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'inventory_manual_adjustment')->count())->toBe(0);
});

it('rejects actor outside the inventory tenant', function (): void {
    $inventoryItem = createManualAdjustmentInventoryItem();
    $actor = User::factory()->create();

    expect(fn () => app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 1,
        reason: 'Wrong tenant.',
    ))->toThrow(ValidationException::class);

    expect($inventoryItem->refresh()->quantity)->toBe(10)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'inventory_manual_adjustment')->count())->toBe(0);
});

it('rejects adjustment that would make quantity negative', function (): void {
    [$inventoryItem, $actor] = createManualAdjustmentInventoryItemAndActor(['quantity' => 2]);

    expect(fn () => app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: -3,
        reason: 'Negative quantity.',
    ))->toThrow(ValidationException::class);

    expect($inventoryItem->refresh()->quantity)->toBe(2)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'inventory_manual_adjustment')->count())->toBe(0);
});

it('rejects adjustment that would make reserved quantity negative', function (): void {
    [$inventoryItem, $actor] = createManualAdjustmentInventoryItemAndActor([
        'quantity' => 10,
        'reserved_quantity' => 1,
    ]);

    expect(fn () => app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 0,
        reservedDelta: -2,
        reason: 'Negative reserved.',
    ))->toThrow(ValidationException::class);

    expect($inventoryItem->refresh()->reserved_quantity)->toBe(1)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'inventory_manual_adjustment')->count())->toBe(0);
});

it('rejects reserved quantity above quantity when backorders are disabled', function (): void {
    [$inventoryItem, $actor] = createManualAdjustmentInventoryItemAndActor([
        'quantity' => 5,
        'reserved_quantity' => 4,
        'allow_backorders' => false,
    ]);

    expect(fn () => app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 0,
        reservedDelta: 2,
        reason: 'Reservation exceeds stock.',
    ))->toThrow(ValidationException::class);

    expect($inventoryItem->refresh()->reserved_quantity)->toBe(4)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'inventory_manual_adjustment')->count())->toBe(0);
});

it('allows reserved quantity above quantity when backorders are enabled', function (): void {
    [$inventoryItem, $actor] = createManualAdjustmentInventoryItemAndActor([
        'quantity' => 5,
        'reserved_quantity' => 4,
        'allow_backorders' => true,
    ]);

    $movement = app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 0,
        reservedDelta: 3,
        reason: 'Backorder reservation reconciliation.',
    );

    expect($inventoryItem->refresh()->quantity)->toBe(5)
        ->and($inventoryItem->reserved_quantity)->toBe(7)
        ->and($movement->reserved_delta)->toBe(3)
        ->and($movement->balance_quantity_after)->toBe(5)
        ->and($movement->balance_reserved_after)->toBe(7);
});

it('strips sensitive metadata keys from stock movement and audit log', function (): void {
    [$inventoryItem, $actor] = createManualAdjustmentInventoryItemAndActor();

    $movement = app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 1,
        reason: 'Metadata sanitizer check.',
        metadata: [
            'safe_note' => 'cycle-count',
            'phone' => '0555123456',
            'customer_phone' => '0555987654',
            'idempotency_key' => 'raw-idempotency-key',
            'payment_proof' => 'proofs/raw-file.jpg',
            'nested' => [
                'token' => 'raw-token',
                'safe_nested_note' => 'kept',
            ],
        ],
    );
    $auditLog = AuditLog::query()
        ->where('event', 'inventory_manual_adjustment')
        ->where('auditable_id', $inventoryItem->id)
        ->firstOrFail();
    $payload = json_encode([
        'movement' => $movement->metadata,
        'audit' => $auditLog->metadata,
    ]);

    expect($movement->metadata['context'])->toMatchArray([
        'safe_note' => 'cycle-count',
        'nested' => ['safe_nested_note' => 'kept'],
    ])
        ->and($auditLog->metadata['context'])->toMatchArray([
            'safe_note' => 'cycle-count',
            'nested' => ['safe_nested_note' => 'kept'],
        ])
        ->and($payload)->not->toContain('0555123456')
        ->and($payload)->not->toContain('0555987654')
        ->and($payload)->not->toContain('raw-idempotency-key')
        ->and($payload)->not->toContain('proofs/raw-file.jpg')
        ->and($payload)->not->toContain('raw-token');
});

it('does not touch orders returns or checkout idempotency flows', function (): void {
    [$inventoryItem, $actor] = createManualAdjustmentInventoryItemAndActor();

    expect(Order::query()->count())->toBe(0)
        ->and(OrderReturn::query()->count())->toBe(0)
        ->and(CheckoutIdempotencyRecord::query()->count())->toBe(0);

    app(AdjustInventoryManually::class)->handle(
        inventoryItem: $inventoryItem,
        actor: $actor,
        quantityDelta: 1,
        reason: 'Standalone manual adjustment.',
    );

    expect(Order::query()->count())->toBe(0)
        ->and(OrderReturn::query()->count())->toBe(0)
        ->and(CheckoutIdempotencyRecord::query()->count())->toBe(0);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function createManualAdjustmentInventoryItem(array $attributes = []): InventoryItem
{
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    return InventoryItem::factory()
        ->forProduct($product)
        ->create(array_merge([
            'quantity' => 10,
            'reserved_quantity' => 2,
            'allow_backorders' => false,
        ], $attributes));
}

function createManualAdjustmentActor(Tenant $tenant): User
{
    $actor = User::factory()->create();

    $tenant->users()->attach($actor, [
        'role' => TenantRole::StoreAdmin->value,
        'permissions' => null,
    ]);

    return $actor;
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array{0: InventoryItem, 1: User}
 */
function createManualAdjustmentInventoryItemAndActor(array $attributes = []): array
{
    $inventoryItem = createManualAdjustmentInventoryItem($attributes);

    return [$inventoryItem, createManualAdjustmentActor($inventoryItem->tenant)];
}
