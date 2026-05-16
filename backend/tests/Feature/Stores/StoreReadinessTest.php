<?php

use App\Actions\Billing\StartTenantSubscription;
use App\Actions\Stores\EvaluateStoreReadiness;
use App\Enums\StoreStatus;
use App\Enums\TenantStatus;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Models\StoreSetting;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use Database\Seeders\AlgeriaGeographySeeder;

beforeEach(function (): void {
    $this->seed(AlgeriaGeographySeeder::class);
});

it('marks a complete store as ready without changing publish behavior', function (): void {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    $plan = createStoreReadinessPlan();

    app(StartTenantSubscription::class)->handle($tenant, $plan, createInvoice: false);

    $store = Store::factory()
        ->for($tenant)
        ->create([
            'name' => 'Demo Store',
            'slug' => 'demo-store',
            'subdomain' => 'demo-store',
            'status' => StoreStatus::Active,
        ]);

    StoreSetting::factory()->forStore($store)->create();
    ThemeSetting::factory()->forStore($store)->create(['is_active' => true]);
    PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);
    ShippingRate::factory()->create(['tenant_id' => $tenant->id]);
    Product::factory()->create(['tenant_id' => $tenant->id, 'published_at' => now()->subDay()]);

    $result = app(EvaluateStoreReadiness::class)->handle($store->fresh());
    $checks = collect($result['checks'])->keyBy('key');

    expect($result['ready'])->toBeTrue()
        ->and($result['missing_required_count'])->toBe(0)
        ->and($result['missing_recommended_count'])->toBe(0)
        ->and($checks->get('payments.cash_on_delivery')['passed'])->toBeTrue()
        ->and($checks->get('catalog.published_product')['passed'])->toBeTrue()
        ->and($store->fresh()->status)->toBe(StoreStatus::Active);
});

it('reports missing checks and ignores other tenant records', function (): void {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Suspended]);
    $otherTenant = Tenant::factory()->create(['status' => TenantStatus::Active]);

    PaymentMethod::factory()->create(['tenant_id' => $otherTenant->id]);
    ShippingRate::factory()->create(['tenant_id' => $otherTenant->id]);
    Product::factory()->create(['tenant_id' => $otherTenant->id, 'published_at' => now()->subDay()]);

    $store = Store::factory()
        ->for($tenant)
        ->create([
            'name' => '',
            'slug' => '',
            'domain' => null,
            'subdomain' => null,
            'status' => StoreStatus::Draft,
        ]);

    $result = app(EvaluateStoreReadiness::class)->handle($store);
    $checks = collect($result['checks'])->keyBy('key');

    expect($result['ready'])->toBeFalse()
        ->and($result['missing_required_count'])->toBeGreaterThan(0)
        ->and($result['missing_recommended_count'])->toBeGreaterThan(0)
        ->and($checks->get('store.public_name')['passed'])->toBeFalse()
        ->and($checks->get('store.route_identifier')['passed'])->toBeFalse()
        ->and($checks->get('payments.cash_on_delivery')['passed'])->toBeFalse()
        ->and($checks->get('shipping.active_rate')['passed'])->toBeFalse()
        ->and($checks->get('catalog.published_product')['passed'])->toBeFalse()
        ->and($checks->get('tenant.operational_status')['passed'])->toBeFalse()
        ->and($checks->get('subscription.feature_access')['passed'])->toBeFalse();
});

function createStoreReadinessPlan(): Plan
{
    return Plan::query()->create([
        'name' => 'Store Readiness Plan',
        'slug' => 'store-readiness',
        'price_minor' => 0,
        'currency' => 'DZD',
        'billing_interval' => 'monthly',
        'is_active' => true,
        'sort_order' => 10,
        'metadata' => [],
    ]);
}
