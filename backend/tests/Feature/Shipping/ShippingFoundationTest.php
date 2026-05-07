<?php

use App\Actions\Shipping\CreateShipmentForOrder;
use App\Actions\Shipping\MarkShipmentDelivered;
use App\Actions\Shipping\MarkShipmentFailed;
use App\Actions\Shipping\MarkShipmentInTransit;
use App\Actions\Shipping\MarkShipmentOutForDelivery;
use App\Actions\Shipping\MarkShipmentShipped;
use App\Actions\Shipping\RetryShipmentDelivery;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\TenantRole;
use App\Models\Commune;
use App\Models\Customer;
use App\Models\FailedDeliveryReason;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Shipment;
use App\Models\ShippingCompany;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wilaya;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->wilaya = Wilaya::query()->findOrFail(16);
    $this->commune = Commune::query()
        ->where('wilaya_id', $this->wilaya->id)
        ->firstOrFail();
});

it('scopes shipping companies to the current tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $shippingCompany = ShippingCompany::factory()->create(['tenant_id' => $tenant->id]);
    ShippingCompany::factory()->create(['tenant_id' => $otherTenant->id]);

    $currentTenant = app(CurrentTenant::class);
    $currentTenant->set($tenant);

    try {
        expect(ShippingCompany::query()->pluck('id')->all())->toBe([$shippingCompany->id]);
    } finally {
        $currentTenant->forget();
    }
});

it('records shipment status history when shipment status changes', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = createShippingOrder($tenant, $this->wilaya->id, $this->commune->id);
    $shippingCompany = ShippingCompany::factory()->create(['tenant_id' => $tenant->id]);

    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreAdmin->value,
        'permissions' => null,
    ]);

    $this->actingAs($user);

    $shipment = Shipment::factory()->forOrder($order)->create([
        'shipping_company_id' => $shippingCompany->id,
        'status' => ShipmentStatus::Pending,
    ]);

    $shipment->update(['status' => ShipmentStatus::Shipped]);

    $this->assertDatabaseHas('shipment_status_histories', [
        'tenant_id' => $tenant->id,
        'shipment_id' => $shipment->id,
        'from_status' => null,
        'to_status' => ShipmentStatus::Pending->value,
        'changed_by_id' => $user->id,
    ]);
    $this->assertDatabaseHas('shipment_status_histories', [
        'tenant_id' => $tenant->id,
        'shipment_id' => $shipment->id,
        'from_status' => ShipmentStatus::Pending->value,
        'to_status' => ShipmentStatus::Shipped->value,
        'changed_by_id' => $user->id,
    ]);
});

it('marks a shipment as failed delivery and updates the order status', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = createShippingOrder($tenant, $this->wilaya->id, $this->commune->id);
    $shippingCompany = ShippingCompany::factory()->create(['tenant_id' => $tenant->id]);
    $reason = FailedDeliveryReason::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'customer-unreachable',
    ]);
    $shipment = Shipment::factory()->forOrder($order)->create([
        'shipping_company_id' => $shippingCompany->id,
        'status' => ShipmentStatus::OutForDelivery,
    ]);

    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    $this->actingAs($user);

    app(MarkShipmentFailed::class)->handle($shipment, $reason, 'Customer did not answer phone.');

    $this->assertDatabaseHas('shipments', [
        'id' => $shipment->id,
        'tenant_id' => $tenant->id,
        'status' => ShipmentStatus::FailedDelivery->value,
        'failed_delivery_reason_id' => $reason->id,
        'failure_note' => 'Customer did not answer phone.',
    ]);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'tenant_id' => $tenant->id,
        'status' => OrderStatus::FailedDelivery->value,
    ]);
    $this->assertDatabaseHas('shipment_status_histories', [
        'shipment_id' => $shipment->id,
        'from_status' => ShipmentStatus::OutForDelivery->value,
        'to_status' => ShipmentStatus::FailedDelivery->value,
        'changed_by_id' => $user->id,
    ]);
    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'from_status' => OrderStatus::Pending->value,
        'to_status' => OrderStatus::FailedDelivery->value,
        'changed_by_id' => $user->id,
    ]);
});

it('creates a shipment from a confirmed order and moves the order to packed', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = createShippingOrder($tenant, $this->wilaya->id, $this->commune->id);
    $order->update(['status' => OrderStatus::Confirmed]);
    $shippingCompany = ShippingCompany::factory()->create([
        'tenant_id' => $tenant->id,
        'tracking_url_template' => 'https://tracking.test/{tracking_number}',
    ]);

    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    $this->actingAs($user);

    $shipment = app(CreateShipmentForOrder::class)->handle($order, $shippingCompany);

    $shipment->refresh();
    $order->refresh();

    expect($shipment->status)->toBe(ShipmentStatus::ReadyToShip)
        ->and($shipment->tracking_number)->toStartWith('SHP-')
        ->and($shipment->trackingUrl())->toBe('https://tracking.test/'.$shipment->tracking_number)
        ->and($shipment->destination_address)->toBe($order->shipping_address)
        ->and($order->status)->toBe(OrderStatus::Packed);

    $this->assertDatabaseHas('shipment_status_histories', [
        'shipment_id' => $shipment->id,
        'from_status' => null,
        'to_status' => ShipmentStatus::ReadyToShip->value,
        'changed_by_id' => $user->id,
    ]);
    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'from_status' => OrderStatus::Confirmed->value,
        'to_status' => OrderStatus::Packed->value,
        'changed_by_id' => $user->id,
    ]);
});

it('rejects creating a shipment with an unsupported shipping company', function (): void {
    $tenant = Tenant::factory()->create();
    $order = createShippingOrder($tenant, $this->wilaya->id, $this->commune->id);
    $order->update(['status' => OrderStatus::Confirmed]);
    $shippingCompany = ShippingCompany::factory()->create([
        'tenant_id' => $tenant->id,
        'supports_home_delivery' => false,
        'supports_desk_delivery' => true,
    ]);

    app(CreateShipmentForOrder::class)->handle($order, $shippingCompany);
})->throws(
    ValidationException::class,
    'The selected shipping company does not support this delivery type.',
);

it('transitions shipment delivery states and synchronizes the order status', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = createShippingOrder($tenant, $this->wilaya->id, $this->commune->id);
    $order->update(['status' => OrderStatus::Packed]);
    $shippingCompany = ShippingCompany::factory()->create(['tenant_id' => $tenant->id]);
    $shipment = Shipment::factory()->forOrder($order)->create([
        'shipping_company_id' => $shippingCompany->id,
        'status' => ShipmentStatus::ReadyToShip,
    ]);

    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    $this->actingAs($user);

    app(MarkShipmentShipped::class)->handle($shipment);
    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Shipped)
        ->and($shipment->shipped_at)->not->toBeNull()
        ->and($order->refresh()->status)->toBe(OrderStatus::Shipped);

    app(MarkShipmentInTransit::class)->handle($shipment);
    expect($shipment->refresh()->status)->toBe(ShipmentStatus::InTransit)
        ->and($order->refresh()->status)->toBe(OrderStatus::Shipped);

    app(MarkShipmentOutForDelivery::class)->handle($shipment);
    expect($shipment->refresh()->status)->toBe(ShipmentStatus::OutForDelivery)
        ->and($order->refresh()->status)->toBe(OrderStatus::OutForDelivery);

    app(MarkShipmentDelivered::class)->handle($shipment);
    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->delivered_at)->not->toBeNull()
        ->and($order->refresh()->status)->toBe(OrderStatus::Delivered);
});

it('retries a failed shipment and clears failure details', function (): void {
    $tenant = Tenant::factory()->create();
    $order = createShippingOrder($tenant, $this->wilaya->id, $this->commune->id);
    $order->update(['status' => OrderStatus::FailedDelivery]);
    $reason = FailedDeliveryReason::factory()->create(['tenant_id' => $tenant->id]);
    $shipment = Shipment::factory()->forOrder($order)->create([
        'shipping_company_id' => ShippingCompany::factory()->create(['tenant_id' => $tenant->id])->id,
        'status' => ShipmentStatus::FailedDelivery,
        'failed_delivery_reason_id' => $reason->id,
        'failure_note' => 'Customer unreachable.',
    ]);

    app(RetryShipmentDelivery::class)->handle($shipment);

    $shipment->refresh();

    expect($shipment->status)->toBe(ShipmentStatus::OutForDelivery)
        ->and($shipment->failed_delivery_reason_id)->toBeNull()
        ->and($shipment->failure_note)->toBeNull()
        ->and($order->refresh()->status)->toBe(OrderStatus::OutForDelivery);
});

it('rejects marking failed delivery with a reason from another tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $order = createShippingOrder($tenant, $this->wilaya->id, $this->commune->id);
    $shippingCompany = ShippingCompany::factory()->create(['tenant_id' => $tenant->id]);
    $reason = FailedDeliveryReason::factory()->create(['tenant_id' => $otherTenant->id]);
    $shipment = Shipment::factory()->forOrder($order)->create([
        'shipping_company_id' => $shippingCompany->id,
    ]);

    app(MarkShipmentFailed::class)->handle($shipment, $reason);
})->throws(
    ValidationException::class,
    'The failed delivery reason does not belong to this shipment tenant.',
);

it('generates return numbers when creating order returns', function (): void {
    $tenant = Tenant::factory()->create();
    $order = createShippingOrder($tenant, $this->wilaya->id, $this->commune->id);

    $orderReturn = OrderReturn::factory()->forOrder($order)->create([
        'return_number' => null,
    ]);

    expect($orderReturn->return_number)->toStartWith('RET-');

    $this->assertDatabaseHas('order_returns', [
        'id' => $orderReturn->id,
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'return_number' => $orderReturn->return_number,
    ]);
});

it('prevents a vendor from viewing another tenant shipment', function (): void {
    $vendor = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $otherOrder = createShippingOrder($otherTenant, $this->wilaya->id, $this->commune->id);
    $shipment = Shipment::factory()->forOrder($otherOrder)->create([
        'shipping_company_id' => ShippingCompany::factory()->create(['tenant_id' => $otherTenant->id])->id,
    ]);

    $tenant->users()->attach($vendor, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);

    expect($vendor->can('view', $shipment))->toBeFalse();
});

function createShippingOrder(Tenant $tenant, int $wilayaId, int $communeId): Order
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
        'status' => OrderStatus::Pending,
        'wilaya_id' => $wilayaId,
        'commune_id' => $communeId,
    ]);
}
