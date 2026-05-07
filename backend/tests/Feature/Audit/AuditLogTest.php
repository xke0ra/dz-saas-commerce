<?php

use App\Actions\Tenancy\InviteTenantUser;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\Wilaya;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->wilaya = Wilaya::query()->findOrFail(16);
    $this->commune = Commune::query()
        ->where('wilaya_id', $this->wilaya->id)
        ->firstOrFail();
});

it('records tenant creation and tenant suspension audit logs', function (): void {
    $actor = User::factory()->create();

    $this->actingAs($actor);

    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    $tenant->update(['status' => TenantStatus::Suspended]);

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'actor_id' => $actor->id,
        'event' => 'tenant.created',
        'auditable_type' => $tenant->getMorphClass(),
        'auditable_id' => $tenant->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'actor_id' => $actor->id,
        'event' => 'tenant.suspended',
        'auditable_type' => $tenant->getMorphClass(),
        'auditable_id' => $tenant->id,
    ]);

    expect(AuditLog::query()->where('event', 'tenant.suspended')->firstOrFail()->new_values)
        ->toMatchArray(['status' => TenantStatus::Suspended->value]);
});

it('records store suspension audit logs', function (): void {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create(['status' => StoreStatus::Active]);

    $store->update(['status' => StoreStatus::Suspended]);

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'event' => 'store.suspended',
        'auditable_type' => $store->getMorphClass(),
        'auditable_id' => $store->id,
    ]);
});

it('records order status changes with actor and old/new values', function (): void {
    $actor = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $order = createAuditedOrder($tenant, $this->wilaya->id, $this->commune->id);

    $this->actingAs($actor);

    $order->update(['status' => OrderStatus::Confirmed]);

    $auditLog = AuditLog::query()
        ->where('event', 'order.status_changed')
        ->where('auditable_id', $order->id)
        ->firstOrFail();

    expect($auditLog->actor_id)->toBe($actor->id)
        ->and($auditLog->tenant_id)->toBe($tenant->id)
        ->and($auditLog->old_values)->toMatchArray(['status' => OrderStatus::Pending->value])
        ->and($auditLog->new_values)->toMatchArray(['status' => OrderStatus::Confirmed->value]);
});

it('records product deletion audit logs', function (): void {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => ProductStatus::Active,
    ]);
    $productId = $product->id;

    $product->delete();

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'event' => 'product.deleted',
        'auditable_type' => $product->getMorphClass(),
        'auditable_id' => $productId,
    ]);
});

it('records payment confirmation audit logs', function (): void {
    $tenant = Tenant::factory()->create();
    $order = createAuditedOrder($tenant, $this->wilaya->id, $this->commune->id);
    $paymentMethod = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);
    $payment = Payment::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => PaymentStatus::Pending,
        'amount_minor' => 150000,
        'currency' => 'DZD',
        'metadata' => [],
    ]);

    $payment->update([
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'event' => 'payment.confirmed',
        'auditable_type' => $payment->getMorphClass(),
        'auditable_id' => $payment->id,
    ]);
});

it('records staff invitation and staff permission changes', function (): void {
    Notification::fake();

    $actor = User::factory()->create();
    $staff = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($staff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);
    $membership = TenantUser::query()
        ->withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $staff->id)
        ->firstOrFail();

    app(InviteTenantUser::class)->handle(
        tenant: $tenant,
        email: 'new-staff@example.test',
        role: TenantRole::StoreStaff,
        permissions: [TenantPermission::ProductsCreate->value],
        invitedBy: $actor,
    );
    $membership->update([
        'permissions' => [TenantPermission::ProductsCreate->value],
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'actor_id' => $actor->id,
        'event' => 'user.invited',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'event' => 'staff.permission_changed',
        'auditable_type' => $membership->getMorphClass(),
        'auditable_id' => (string) $membership->id,
    ]);
});

function createAuditedOrder(Tenant $tenant, int $wilayaId, int $communeId): Order
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
