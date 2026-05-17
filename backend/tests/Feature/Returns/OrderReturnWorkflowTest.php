<?php

use App\Actions\Inventory\ReleaseOrderInventoryReservations;
use App\Actions\Inventory\SettleOrderInventory;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\MarkOrderDelivered;
use App\Actions\Returns\ReceiveOrderReturn;
use App\Actions\Returns\RefundOrderReturn;
use App\Actions\Returns\TransitionOrderReturnStatus;
use App\Enums\OrderReturnStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Commune;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wilaya;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->wilaya = Wilaya::query()->findOrFail(16);
    $this->commune = Commune::query()
        ->where('wilaya_id', $this->wilaya->id)
        ->firstOrFail();
});

it('settles delivered inventory and restocks it once when a return is received', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    [$order, $inventoryItem] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::OutForDelivery,
    );

    attachReturnWorkflowUser($user, $tenant);

    $this->actingAs($user);

    app(MarkOrderDelivered::class)->handle($order);

    $inventoryItem->refresh();
    $order->refresh();
    $orderItem = $order->items()->firstOrFail();
    $settlementMovement = StockMovement::query()
        ->withoutGlobalScope('current_tenant')
        ->where('order_id', $order->id)
        ->where('type', StockMovementType::Settled->value)
        ->firstOrFail();
    $settlementMetadata = $settlementMovement->metadata;

    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->metadata['inventory_settled_at'])->not->toBeNull()
        ->and($inventoryItem->quantity)->toBe(8)
        ->and($inventoryItem->reserved_quantity)->toBe(0)
        ->and($settlementMovement->tenant_id)->toBe($tenant->id)
        ->and($settlementMovement->product_id)->toBe($orderItem->product_id)
        ->and($settlementMovement->inventory_item_id)->toBe($inventoryItem->id)
        ->and($settlementMovement->order_id)->toBe($order->id)
        ->and($settlementMovement->order_item_id)->toBe($orderItem->id)
        ->and($settlementMovement->order_return_id)->toBeNull()
        ->and($settlementMovement->actor_id)->toBeNull()
        ->and($settlementMovement->type)->toBe(StockMovementType::Settled)
        ->and($settlementMovement->quantity_delta)->toBe(-2)
        ->and($settlementMovement->reserved_delta)->toBe(-2)
        ->and($settlementMovement->balance_quantity_after)->toBe(8)
        ->and($settlementMovement->balance_reserved_after)->toBe(0)
        ->and($settlementMovement->reason)->toBe('order_inventory_settlement')
        ->and($settlementMetadata)->toMatchArray([
            'source' => 'settle_order_inventory',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'product_id' => $orderItem->product_id,
            'order_item_id' => $orderItem->id,
        ])
        ->and(json_encode($settlementMetadata))->not->toContain($order->customer()->value('phone'));

    app(SettleOrderInventory::class)->handle($order->refresh());
    app(MarkOrderDelivered::class)->handle($order->refresh());

    expect(StockMovement::query()
        ->withoutGlobalScope('current_tenant')
        ->where('order_id', $order->id)
        ->where('type', StockMovementType::Settled->value)
        ->count())->toBe(1);

    $orderReturn = OrderReturn::factory()->forOrder($order)->create([
        'status' => OrderReturnStatus::Approved,
    ]);

    app(ReceiveOrderReturn::class)->handle($orderReturn, restock: true, resolutionNote: 'Returned item received.');

    $inventoryItem->refresh();
    $orderReturn->refresh();
    $order->refresh();

    expect($orderReturn->status)->toBe(OrderReturnStatus::Received)
        ->and($orderReturn->metadata['restocked_at'])->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Returned)
        ->and($inventoryItem->quantity)->toBe(10)
        ->and($inventoryItem->reserved_quantity)->toBe(0);

    app(ReceiveOrderReturn::class)->handle($orderReturn, restock: true);

    expect($inventoryItem->refresh()->quantity)->toBe(10);
});

it('does not settle or record stock movement when order inventory is already settled', function (): void {
    $tenant = Tenant::factory()->create();
    [$order, $inventoryItem] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::OutForDelivery,
    );
    $order->update([
        'metadata' => [
            'inventory_settled_at' => now()->toISOString(),
        ],
    ]);

    app(SettleOrderInventory::class)->handle($order);

    expect($inventoryItem->refresh()->quantity)->toBe(10)
        ->and($inventoryItem->reserved_quantity)->toBe(2)
        ->and(StockMovement::query()
            ->withoutGlobalScope('current_tenant')
            ->where('order_id', $order->id)
            ->where('type', StockMovementType::Settled->value)
            ->count())->toBe(0);
});

it('records only the actual settled quantity when stock is below the order item quantity', function (): void {
    $tenant = Tenant::factory()->create();
    [$order, $inventoryItem] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::OutForDelivery,
        inventoryQuantity: 1,
        reservedQuantity: 1,
        itemQuantity: 3,
    );

    app(SettleOrderInventory::class)->handle($order);

    $movement = StockMovement::query()
        ->withoutGlobalScope('current_tenant')
        ->where('order_id', $order->id)
        ->where('type', StockMovementType::Settled->value)
        ->firstOrFail();

    expect($inventoryItem->refresh()->quantity)->toBe(0)
        ->and($inventoryItem->reserved_quantity)->toBe(0)
        ->and($movement->quantity_delta)->toBe(-1)
        ->and($movement->reserved_delta)->toBe(-1)
        ->and($movement->balance_quantity_after)->toBe(0)
        ->and($movement->balance_reserved_after)->toBe(0);
});

it('records only the actual settled reserved quantity when reservations are below the order item quantity', function (): void {
    $tenant = Tenant::factory()->create();
    [$order, $inventoryItem] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::OutForDelivery,
        inventoryQuantity: 5,
        reservedQuantity: 1,
        itemQuantity: 3,
    );

    app(SettleOrderInventory::class)->handle($order);

    $movement = StockMovement::query()
        ->withoutGlobalScope('current_tenant')
        ->where('order_id', $order->id)
        ->where('type', StockMovementType::Settled->value)
        ->firstOrFail();

    expect($inventoryItem->refresh()->quantity)->toBe(2)
        ->and($inventoryItem->reserved_quantity)->toBe(0)
        ->and($movement->quantity_delta)->toBe(-3)
        ->and($movement->reserved_delta)->toBe(-1)
        ->and($movement->balance_quantity_after)->toBe(2)
        ->and($movement->balance_reserved_after)->toBe(0);
});

it('does not record settlement movements when inventory is missing or not tracked', function (): void {
    $tenant = Tenant::factory()->create();
    [$orderWithoutInventory, $deletedInventory] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::OutForDelivery,
    );
    $deletedInventory->delete();
    [$orderWithUntrackedInventory, $untrackedInventory] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::OutForDelivery,
        trackQuantity: false,
    );

    app(SettleOrderInventory::class)->handle($orderWithoutInventory);
    app(SettleOrderInventory::class)->handle($orderWithUntrackedInventory);

    expect($untrackedInventory->refresh()->quantity)->toBe(10)
        ->and($untrackedInventory->reserved_quantity)->toBe(2)
        ->and($orderWithoutInventory->refresh()->metadata['inventory_settled_at'])->not->toBeNull()
        ->and($orderWithUntrackedInventory->refresh()->metadata['inventory_settled_at'])->not->toBeNull()
        ->and(StockMovement::query()
            ->withoutGlobalScope('current_tenant')
            ->whereIn('order_id', [$orderWithoutInventory->id, $orderWithUntrackedInventory->id])
            ->where('type', StockMovementType::Settled->value)
            ->count())->toBe(0);
});

it('refunds a received return and marks the order payment as refunded', function (): void {
    $tenant = Tenant::factory()->create();
    [$order] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::Returned,
        paymentStatus: PaymentStatus::Paid,
    );
    $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);
    $payment = Payment::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => PaymentStatus::Paid,
        'amount_minor' => $order->total_minor,
        'currency' => $order->currency,
        'reference' => 'COD-PAID-RETURN',
        'metadata' => [],
        'paid_at' => now(),
    ]);
    $orderReturn = OrderReturn::factory()->forOrder($order)->create([
        'status' => OrderReturnStatus::Received,
    ]);

    app(RefundOrderReturn::class)->handle($orderReturn, 'Refund after receiving return.');

    $orderReturn->refresh();
    $order->refresh();
    $payment->refresh();

    expect($orderReturn->status)->toBe(OrderReturnStatus::Refunded)
        ->and($orderReturn->resolved_at)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Refunded)
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->metadata['refund_reason'])->toBe('Refund after receiving return.');
});

it('releases reserved inventory when an order is cancelled before delivery', function (): void {
    $tenant = Tenant::factory()->create();
    [$order, $inventoryItem] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::Pending,
    );

    app(CancelOrder::class)->handle($order);

    $order->refresh();
    $inventoryItem->refresh();
    $orderItem = $order->items()->firstOrFail();
    $movement = StockMovement::query()
        ->withoutGlobalScope('current_tenant')
        ->where('order_id', $order->id)
        ->where('type', StockMovementType::Released->value)
        ->firstOrFail();
    $metadata = $movement->metadata;

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->metadata['inventory_released_at'])->not->toBeNull()
        ->and($inventoryItem->quantity)->toBe(10)
        ->and($inventoryItem->reserved_quantity)->toBe(0)
        ->and($movement->tenant_id)->toBe($tenant->id)
        ->and($movement->product_id)->toBe($orderItem->product_id)
        ->and($movement->inventory_item_id)->toBe($inventoryItem->id)
        ->and($movement->order_id)->toBe($order->id)
        ->and($movement->order_item_id)->toBe($orderItem->id)
        ->and($movement->order_return_id)->toBeNull()
        ->and($movement->actor_id)->toBeNull()
        ->and($movement->type)->toBe(StockMovementType::Released)
        ->and($movement->quantity_delta)->toBe(0)
        ->and($movement->reserved_delta)->toBe(-2)
        ->and($movement->balance_quantity_after)->toBe(10)
        ->and($movement->balance_reserved_after)->toBe(0)
        ->and($movement->reason)->toBe('order_inventory_release')
        ->and($metadata)->toMatchArray([
            'source' => 'release_order_inventory_reservations',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'product_id' => $orderItem->product_id,
            'order_item_id' => $orderItem->id,
        ])
        ->and(json_encode($metadata))->not->toContain($order->customer()->value('phone'));

    app(ReleaseOrderInventoryReservations::class)->handle($order->refresh());
    app(CancelOrder::class)->handle($order->refresh());

    expect(StockMovement::query()
        ->withoutGlobalScope('current_tenant')
        ->where('order_id', $order->id)
        ->where('type', StockMovementType::Released->value)
        ->count())->toBe(1);
});

it('does not release or record stock movement when order inventory is already settled', function (): void {
    $tenant = Tenant::factory()->create();
    [$order, $inventoryItem] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::Pending,
    );
    $order->update([
        'metadata' => [
            'inventory_settled_at' => now()->toISOString(),
        ],
    ]);

    app(ReleaseOrderInventoryReservations::class)->handle($order);

    expect($inventoryItem->refresh()->reserved_quantity)->toBe(2)
        ->and(StockMovement::query()
            ->withoutGlobalScope('current_tenant')
            ->where('order_id', $order->id)
            ->where('type', StockMovementType::Released->value)
            ->count())->toBe(0);
});

it('records only the actual released reserved quantity', function (): void {
    $tenant = Tenant::factory()->create();
    [$order, $inventoryItem] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::Pending,
        reservedQuantity: 1,
        itemQuantity: 3,
    );

    app(ReleaseOrderInventoryReservations::class)->handle($order);

    $movement = StockMovement::query()
        ->withoutGlobalScope('current_tenant')
        ->where('order_id', $order->id)
        ->where('type', StockMovementType::Released->value)
        ->firstOrFail();

    expect($inventoryItem->refresh()->reserved_quantity)->toBe(0)
        ->and($movement->reserved_delta)->toBe(-1)
        ->and($movement->balance_quantity_after)->toBe(10)
        ->and($movement->balance_reserved_after)->toBe(0);
});

it('does not record release movements when inventory is missing or not tracked', function (): void {
    $tenant = Tenant::factory()->create();
    [$orderWithoutInventory, $deletedInventory] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::Pending,
    );
    $deletedInventory->delete();
    [$orderWithUntrackedInventory, $untrackedInventory] = createReturnWorkflowOrderWithInventory(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::Pending,
        trackQuantity: false,
    );

    app(ReleaseOrderInventoryReservations::class)->handle($orderWithoutInventory);
    app(ReleaseOrderInventoryReservations::class)->handle($orderWithUntrackedInventory);

    expect($untrackedInventory->refresh()->reserved_quantity)->toBe(2)
        ->and($orderWithoutInventory->refresh()->metadata['inventory_released_at'])->not->toBeNull()
        ->and($orderWithUntrackedInventory->refresh()->metadata['inventory_released_at'])->not->toBeNull()
        ->and(StockMovement::query()
            ->withoutGlobalScope('current_tenant')
            ->whereIn('order_id', [$orderWithoutInventory->id, $orderWithUntrackedInventory->id])
            ->where('type', StockMovementType::Released->value)
            ->count())->toBe(0);
});

it('rejects invalid return status transitions', function (): void {
    $tenant = Tenant::factory()->create();
    [$order] = createReturnWorkflowOrderWithInventory($tenant, $this->wilaya->id, $this->commune->id);
    $orderReturn = OrderReturn::factory()->forOrder($order)->create([
        'status' => OrderReturnStatus::Requested,
    ]);

    app(TransitionOrderReturnStatus::class)->handle($orderReturn, OrderReturnStatus::Refunded);
})->throws(
    ValidationException::class,
    'Cannot transition return status from Requested to Refunded.',
);

it('protects return workflow actions with return update permissions', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    [$order] = createReturnWorkflowOrderWithInventory($tenant, $this->wilaya->id, $this->commune->id);
    [$otherOrder] = createReturnWorkflowOrderWithInventory($otherTenant, $this->wilaya->id, $this->commune->id);
    $orderReturn = OrderReturn::factory()->forOrder($order)->create();
    $otherReturn = OrderReturn::factory()->forOrder($otherOrder)->create();

    attachReturnWorkflowUser($user, $tenant, permissions: [
        TenantPermission::ReturnsUpdate->value => false,
    ]);

    expect($user->can('approve', $orderReturn))->toBeFalse()
        ->and($user->can('receive', $orderReturn))->toBeFalse()
        ->and($user->can('refund', $orderReturn))->toBeFalse()
        ->and($user->can('approve', $otherReturn))->toBeFalse();
});

/**
 * @return array{0: Order, 1: InventoryItem}
 */
function createReturnWorkflowOrderWithInventory(
    Tenant $tenant,
    int $wilayaId,
    int $communeId,
    OrderStatus $status = OrderStatus::Delivered,
    PaymentStatus $paymentStatus = PaymentStatus::Unpaid,
    int $inventoryQuantity = 10,
    int $reservedQuantity = 2,
    int $itemQuantity = 2,
    bool $trackQuantity = true,
): array {
    $store = Store::factory()->for($tenant)->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
    ]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'price_minor' => 120000,
    ]);
    $inventoryItem = InventoryItem::factory()->forProduct($product)->create([
        'quantity' => $inventoryQuantity,
        'reserved_quantity' => $reservedQuantity,
        'track_quantity' => $trackQuantity,
    ]);
    $subtotal = 120000 * $itemQuantity;
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'status' => $status,
        'payment_status' => $paymentStatus,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
        'subtotal_minor' => $subtotal,
        'shipping_fee_minor' => 50000,
        'discount_minor' => 0,
        'total_minor' => $subtotal + 50000,
    ]);

    OrderItem::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'quantity' => $itemQuantity,
        'unit_price_minor' => 120000,
        'total_minor' => $subtotal,
        'metadata' => [],
    ]);

    return [$order, $inventoryItem];
}

/**
 * @param  array<string, bool>|null  $permissions
 */
function attachReturnWorkflowUser(
    User $user,
    Tenant $tenant,
    TenantRole $role = TenantRole::StoreAdmin,
    ?array $permissions = null,
): void {
    $tenant->users()->attach($user, [
        'role' => $role->value,
        'permissions' => $permissions === null ? null : json_encode($permissions),
    ]);
}
