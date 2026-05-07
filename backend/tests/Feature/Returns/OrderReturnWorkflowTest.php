<?php

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\MarkOrderDelivered;
use App\Actions\Returns\ReceiveOrderReturn;
use App\Actions\Returns\RefundOrderReturn;
use App\Actions\Returns\TransitionOrderReturnStatus;
use App\Enums\OrderReturnStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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

    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->metadata['inventory_settled_at'])->not->toBeNull()
        ->and($inventoryItem->quantity)->toBe(8)
        ->and($inventoryItem->reserved_quantity)->toBe(0);

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

    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->metadata['inventory_released_at'])->not->toBeNull()
        ->and($inventoryItem->quantity)->toBe(10)
        ->and($inventoryItem->reserved_quantity)->toBe(0);
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
        'quantity' => 10,
        'reserved_quantity' => 2,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'status' => $status,
        'payment_status' => $paymentStatus,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
        'subtotal_minor' => 240000,
        'shipping_fee_minor' => 50000,
        'discount_minor' => 0,
        'total_minor' => 290000,
    ]);

    OrderItem::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'quantity' => 2,
        'unit_price_minor' => 120000,
        'total_minor' => 240000,
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
