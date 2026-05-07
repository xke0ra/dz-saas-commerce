<?php

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Actions\Orders\MarkOrderDelivered;
use App\Actions\Orders\MarkOrderOutForDelivery;
use App\Actions\Orders\PackOrder;
use App\Actions\Orders\ShipOrder;
use App\Enums\OrderStatus;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Commune;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
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

it('confirms a pending order and records status history', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = createOrderFulfillmentOrder($tenant, $this->wilaya->id, $this->commune->id);

    attachOrderFulfillmentUser($user, $tenant);

    $this->actingAs($user);

    app(ConfirmOrder::class)->handle($order);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Confirmed)
        ->and($order->confirmed_at)->not->toBeNull();

    $this->assertDatabaseHas('order_status_histories', [
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'from_status' => OrderStatus::Pending->value,
        'to_status' => OrderStatus::Confirmed->value,
        'changed_by_id' => $user->id,
    ]);
});

it('moves an order through the fulfillment workflow', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = createOrderFulfillmentOrder(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::Confirmed,
    );

    attachOrderFulfillmentUser($user, $tenant);

    $this->actingAs($user);

    app(PackOrder::class)->handle($order);
    app(ShipOrder::class)->handle($order->refresh());
    app(MarkOrderOutForDelivery::class)->handle($order->refresh());
    app(MarkOrderDelivered::class)->handle($order->refresh());

    expect($order->refresh()->status)->toBe(OrderStatus::Delivered);

    foreach ([OrderStatus::Packed, OrderStatus::Shipped, OrderStatus::OutForDelivery, OrderStatus::Delivered] as $status) {
        $this->assertDatabaseHas('order_status_histories', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'to_status' => $status->value,
            'changed_by_id' => $user->id,
        ]);
    }
});

it('rejects invalid order status transitions', function (): void {
    $tenant = Tenant::factory()->create();
    $order = createOrderFulfillmentOrder(
        tenant: $tenant,
        wilayaId: $this->wilaya->id,
        communeId: $this->commune->id,
        status: OrderStatus::Delivered,
    );

    try {
        app(CancelOrder::class)->handle($order);
    } catch (ValidationException $exception) {
        expect($exception->errors()['status'][0])->toBe('Cannot transition order status from Delivered to Cancelled.');
        expect($order->refresh()->status)->toBe(OrderStatus::Delivered);

        throw $exception;
    }
})->throws(ValidationException::class);

it('enforces granular fulfillment permissions on order policies', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $order = createOrderFulfillmentOrder($tenant, $this->wilaya->id, $this->commune->id);
    $otherOrder = createOrderFulfillmentOrder($otherTenant, $this->wilaya->id, $this->commune->id);

    attachOrderFulfillmentUser($user, $tenant, permissions: [
        TenantPermission::OrdersShip->value => false,
    ]);

    expect($user->can('confirm', $order))->toBeTrue()
        ->and($user->can('cancel', $order))->toBeTrue()
        ->and($user->can('ship', $order))->toBeFalse()
        ->and($user->can('confirm', $otherOrder))->toBeFalse();
});

it('renders a printable order slip for authorized tenant users', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = createOrderFulfillmentOrder($tenant, $this->wilaya->id, $this->commune->id);

    OrderItem::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'product_id' => null,
        'product_name' => 'USB Charger',
        'product_sku' => 'SKU-USB-01',
        'quantity' => 2,
        'unit_price_minor' => 120000,
        'total_minor' => 240000,
        'metadata' => [],
    ]);

    attachOrderFulfillmentUser($user, $tenant);

    $response = $this->actingAs($user)->get(route('vendor.orders.slip', $order));

    $response
        ->assertOk()
        ->assertSee('Print order slip')
        ->assertSee($order->order_number)
        ->assertSee('USB Charger')
        ->assertSee('SKU-USB-01');
});

function createOrderFulfillmentOrder(Tenant $tenant, int $wilayaId, int $communeId, OrderStatus $status = OrderStatus::Pending): Order
{
    $store = Store::factory()->for($tenant)->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
    ]);

    return Order::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'status' => $status,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
    ]);
}

/**
 * @param  array<string, bool>|null  $permissions
 */
function attachOrderFulfillmentUser(
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
