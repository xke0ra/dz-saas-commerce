<?php

use App\Actions\Billing\StartTenantSubscription;
use App\Enums\OrderStatus;
use App\Enums\PlanFeatureKey;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Filament\Vendor\Widgets\DeliveredSalesChartWidget;
use App\Filament\Vendor\Widgets\OrderAnalyticsStatsWidget;
use App\Models\Commune;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wilaya;
use App\Support\Analytics\TenantOrderAnalytics;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);
    $this->travelTo(Carbon::parse('2026-04-25 12:00:00'));
});

afterEach(function (): void {
    $this->travelBack();
});

it('summarizes tenant order analytics without leaking another tenant data', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    [$store, $customer] = analyticsStoreAndCustomer($tenant);
    [$otherStore, $otherCustomer] = analyticsStoreAndCustomer($otherTenant);

    createAnalyticsOrder($tenant, $store, $customer, OrderStatus::Delivered, 120000, now()->subDay());
    createAnalyticsOrder($tenant, $store, $customer, OrderStatus::Pending, 45000, now()->subHours(2));
    createAnalyticsOrder($tenant, $store, $customer, OrderStatus::FailedDelivery, 90000, now()->subDays(3));
    createAnalyticsOrder($tenant, $store, $customer, OrderStatus::Cancelled, 70000, now()->subDays(4));
    createAnalyticsOrder($tenant, $store, $customer, OrderStatus::Delivered, 999999, now()->subDays(45));
    createAnalyticsOrder($otherTenant, $otherStore, $otherCustomer, OrderStatus::Delivered, 888888, now()->subDay());

    $summary = app(TenantOrderAnalytics::class)->summary($tenant);

    expect($summary['orders_count'])->toBe(4)
        ->and($summary['pending_orders_count'])->toBe(1)
        ->and($summary['delivered_orders_count'])->toBe(1)
        ->and($summary['failed_delivery_orders_count'])->toBe(1)
        ->and($summary['cancelled_orders_count'])->toBe(1)
        ->and($summary['delivered_revenue_minor'])->toBe(120000)
        ->and($summary['average_delivered_order_value_minor'])->toBe(120000)
        ->and($summary['failed_delivery_rate'])->toBe(25.0);
});

it('builds daily delivered sales series for the current tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    [$store, $customer] = analyticsStoreAndCustomer($tenant);
    [$otherStore, $otherCustomer] = analyticsStoreAndCustomer($otherTenant);

    createAnalyticsOrder($tenant, $store, $customer, OrderStatus::Delivered, 100000, now());
    createAnalyticsOrder($tenant, $store, $customer, OrderStatus::Delivered, 200000, now()->subDay());
    createAnalyticsOrder($tenant, $store, $customer, OrderStatus::Pending, 300000, now());
    createAnalyticsOrder($otherTenant, $otherStore, $otherCustomer, OrderStatus::Delivered, 900000, now());

    $series = app(TenantOrderAnalytics::class)->dailyDeliveredSales($tenant, days: 3);

    expect($series)->toHaveCount(3)
        ->and($series[1]['delivered_revenue_minor'])->toBe(200000)
        ->and($series[1]['delivered_orders_count'])->toBe(1)
        ->and($series[2]['delivered_revenue_minor'])->toBe(100000)
        ->and($series[2]['delivered_orders_count'])->toBe(1);
});

it('shows vendor analytics widgets only with analytics permission and plan feature', function (): void {
    $admin = User::factory()->create();
    $staff = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $tenant->users()->attach($admin, [
        'role' => TenantRole::StoreAdmin->value,
        'permissions' => null,
    ]);
    $tenant->users()->attach($staff, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => null,
    ]);
    startAnalyticsSubscription($tenant, advancedAnalyticsEnabled: true);

    withAnalyticsTenant($tenant, function () use ($admin, $staff): void {
        $this->actingAs($admin);

        expect(OrderAnalyticsStatsWidget::canView())->toBeTrue()
            ->and(DeliveredSalesChartWidget::canView())->toBeTrue();

        $this->actingAs($staff);

        expect(OrderAnalyticsStatsWidget::canView())->toBeFalse()
            ->and(DeliveredSalesChartWidget::canView())->toBeFalse();
    });
});

it('hides vendor analytics widgets when the subscription feature is disabled', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $tenant->users()->attach($user, [
        'role' => TenantRole::StoreStaff->value,
        'permissions' => json_encode([
            TenantPermission::AnalyticsView->value => true,
        ]),
    ]);
    startAnalyticsSubscription($tenant, advancedAnalyticsEnabled: false);

    withAnalyticsTenant($tenant, function () use ($user): void {
        $this->actingAs($user);

        expect(OrderAnalyticsStatsWidget::canView())->toBeFalse()
            ->and(DeliveredSalesChartWidget::canView())->toBeFalse();
    });
});

it('exposes tenant scoped data from vendor analytics widgets', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    [$store, $customer] = analyticsStoreAndCustomer($tenant);
    [$otherStore, $otherCustomer] = analyticsStoreAndCustomer($otherTenant);

    createAnalyticsOrder($tenant, $store, $customer, OrderStatus::Delivered, 150000, now());
    createAnalyticsOrder($tenant, $store, $customer, OrderStatus::FailedDelivery, 80000, now());
    createAnalyticsOrder($otherTenant, $otherStore, $otherCustomer, OrderStatus::Delivered, 950000, now());

    withAnalyticsTenant($tenant, function (): void {
        $stats = (new OrderAnalyticsStatsWidget)->statsData();
        $chart = (new DeliveredSalesChartWidget)->chartData();
        $chartData = $chart['datasets'][0]['data'];

        expect($stats['orders_count'])->toBe(2)
            ->and($stats['delivered_revenue_minor'])->toBe(150000)
            ->and($chartData[array_key_last($chartData)])->toBe(1500.0);
    });
});

/**
 * @return array{0: Store, 1: Customer}
 */
function analyticsStoreAndCustomer(Tenant $tenant): array
{
    $wilaya = Wilaya::query()->findOrFail(16);
    $commune = Commune::query()
        ->where('wilaya_id', $wilaya->id)
        ->firstOrFail();

    $store = Store::factory()->for($tenant)->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
    ]);

    return [$store, $customer];
}

function createAnalyticsOrder(
    Tenant $tenant,
    Store $store,
    Customer $customer,
    OrderStatus $status,
    int $totalMinor,
    Carbon $createdAt,
): Order {
    return Order::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'customer_id' => $customer->id,
        'status' => $status,
        'subtotal_minor' => $totalMinor,
        'shipping_fee_minor' => 0,
        'discount_minor' => 0,
        'total_minor' => $totalMinor,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

function startAnalyticsSubscription(Tenant $tenant, bool $advancedAnalyticsEnabled): void
{
    $plan = Plan::query()->create([
        'name' => 'Analytics Test',
        'slug' => 'analytics-test-'.str()->random(8),
        'price_minor' => 0,
        'currency' => 'DZD',
        'billing_interval' => 'monthly',
        'is_active' => true,
        'sort_order' => 10,
        'metadata' => [],
    ]);

    PlanFeature::query()->create([
        'plan_id' => $plan->id,
        'key' => PlanFeatureKey::AdvancedAnalytics->value,
        'value' => ['value' => $advancedAnalyticsEnabled],
    ]);

    app(StartTenantSubscription::class)->handle($tenant, $plan, createInvoice: false);
}

function withAnalyticsTenant(Tenant $tenant, Closure $callback): void
{
    $currentTenant = app(CurrentTenant::class);
    $currentTenant->set($tenant);

    try {
        $callback();
    } finally {
        $currentTenant->forget();
    }
}
