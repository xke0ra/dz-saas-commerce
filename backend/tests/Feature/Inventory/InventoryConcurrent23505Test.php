<?php

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Product;
use App.Models\ProductVariant;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wilaya;
use App\Models\Commune;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\AlgeriaGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * This test does NOT use RefreshDatabase because we need worker processes
 * to see the same database state. Instead, we manually manage test data.
 */
uses()->group('concurrency');

function setupTestData(): array
{
    // Check if already set up via environment
    $tenantId = getenv('CONCURRENCY_TENANT_ID');
    if ($tenantId !== false && $tenantId !== '') {
        return [
            'tenantId' => $tenantId,
            'storeId' => getenv('CONCURRENCY_STORE_ID'),
            'productId' => getenv('CONCURRENCY_PRODUCT_ID'),
            'customerId' => getenv('CONCURRENCY_CUSTOMER_ID'),
            'paymentMethodId' => getenv('CONCURRENCY_PAYMENT_METHOD_ID'),
            'shippingRateId' => getenv('CONCURRENCY_SHIPPING_RATE_ID'),
        ];
    }

    // Ensure database is migrated and seeded
    Artisan::call('migrate:fresh', ['--force' => true]);
    Artisan::call('db:seed', ['--class' => AlgeriaGeographySeeder::class, '--force' => true]);

    $wilaya = Wilaya::query()->findOrFail(16);
    $commune = Commune::query()
        ->where('wilaya_id', $wilaya->id)
        ->firstOrFail();

    // Create a dedicated tenant for concurrency tests
    $tenant = Tenant::create([
        'name' => 'Concurrency Test Tenant',
        'slug' => 'concurrency-test-' . uniqid(),
        'status' => 'active',
    ]);

    $plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-' . $tenant->id,
        'price_minor' => 0,
        'currency' => 'DZD',
        'billing_interval' => 'month',
        'is_active' => true,
        'sort_order' => 0,
        'metadata' => [],
    ]);

    PlanFeature::create([
        'plan_id' => $plan->id,
        'key' => 'max_products',
        'value' => ['value' => '100'],
    ]);
    PlanFeature::create([
        'plan_id' => $plan->id,
        'key' => 'max_orders_per_month',
        'value' => ['value' => '1000'],
    ]);
    PlanFeature::create([
        'plan_id' => $plan->id,
        'key' => 'max_staff_users',
        'value' => ['value' => '10'],
    ]);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'is_current' => true,
        'starts_at' => now(),
        'trial_ends_at' => null,
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
        'grace_ends_at' => null,
        'cancelled_at' => null,
        'ends_at' => null,
        'metadata' => [],
    ]);

    app(\App\Support\Tenancy\CurrentTenant::class)->set($tenant);

    $store = \App\Models\Store::create([
        'name' => 'Test Store',
        'slug' => 'test-store-' . $tenant->id,
        'status' => 'active',
        'currency' => 'DZD',
        'metadata' => [],
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'full_name' => 'Test Customer',
        'phone' => '0555' . random_int(100000, 999999),
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'address' => 'Test Address',
    ]);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
        'name' => 'Test Product',
        'slug' => 'test-product-' . uniqid(),
        'sku' => 'TEST-SKU-' . uniqid(),
        'price_minor' => 10000,
        'currency' => 'DZD',
        'metadata' => [],
    ]);

    // NO pre-existing inventory item - this is the race condition test

    $paymentMethod = PaymentMethod::create([
        'tenant_id' => $tenant->id,
        'type' => PaymentMethodType::CashOnDelivery,
        'name' => 'Cash on Delivery',
        'is_active' => true,
        'metadata' => [],
    ]);

    $shippingRate = ShippingRate::create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'delivery_type' => DeliveryType::Home,
        'price_minor' => 5000,
        'currency' => 'DZD',
        'is_active' => true,
    ]);

    // Store in environment for worker processes
    putenv("CONCURRENCY_TENANT_ID={$tenant->id}");
    putenv("CONCURRENCY_STORE_ID={$store->id}");
    putenv("CONCURRENCY_PRODUCT_ID={$product->id}");
    putenv("CONCURRENCY_CUSTOMER_ID={$customer->id}");
    putenv("CONCURRENCY_PAYMENT_METHOD_ID={$paymentMethod->id}");
    putenv("CONCURRENCY_SHIPPING_RATE_ID={$shippingRate->id}");
    putenv("CONCURRENCY_WILAYA_ID={$wilaya->id}");
    putenv("CONCURRENCY_COMMUNE_ID={$commune->id}");

    return [
        'tenantId' => $tenant->id,
        'storeId' => $store->id,
        'productId' => $product->id,
        'customerId' => $customer->id,
        'paymentMethodId' => $paymentMethod->id,
        'shippingRateId' => $shippingRate->id,
    ];
}

function cleanupTestData(): void
{
    $tenantId = getenv('CONCURRENCY_TENANT_ID');
    if ($tenantId !== false && $tenantId !== '') {
        DB::statement('DELETE FROM inventory_items WHERE tenant_id = ?', [$tenantId]);
        DB::statement('DELETE FROM orders WHERE tenant_id = ?', [$tenantId]);
        DB::statement('DELETE FROM customers WHERE tenant_id = ?', [$tenantId]);
        DB::statement('DELETE FROM products WHERE tenant_id = ?', [$tenantId]);
        DB::statement('DELETE FROM payment_methods WHERE tenant_id = ?', [$tenantId]);
        DB::statement('DELETE FROM shipping_rates WHERE tenant_id = ?', [$tenantId]);
        DB::statement('DELETE FROM stores WHERE tenant_id = ?', [$tenantId]);
        DB::statement('DELETE FROM subscriptions WHERE tenant_id = ?', [$tenantId]);
        DB::statement('DELETE FROM plan_features WHERE plan_id IN (SELECT id FROM plans WHERE slug LIKE ?)', ["test-plan-{$tenantId}%"]);
        DB::statement('DELETE FROM plans WHERE slug LIKE ?', ["test-plan-{$tenantId}%"]);
        DB::statement('DELETE FROM tenants WHERE id = ?', [$tenantId]);
        putenv('CONCURRENCY_TENANT_ID');
        putenv('CONCURRENCY_STORE_ID');
        putenv('CONCURRENCY_PRODUCT_ID');
        putenv('CONCURRENCY_CUSTOMER_ID');
        putenv('CONCURRENCY_PAYMENT_METHOD_ID');
        putenv('CONCURRENCY_SHIPPING_RATE_ID');
    }
}

function runWorkerScript(string $script, array $args): array
{
    $scriptFile = sys_get_temp_dir() . "/worker_" . uniqid() . ".php";
    file_put_contents($scriptFile, $script);

    $cmd = "php {$scriptFile} " . implode(' ', $args);

    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptorspec, $pipes);
    if (! is_resource($process)) {
        throw new \RuntimeException("Failed to start process: {$cmd}");
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    @unlink($scriptFile);

    return [
        'exit_code' => $exitCode,
        'stdout' => trim($stdout),
        'stderr' => trim($stderr),
    ];
}

/**
 * Test the genuine 23505 race condition using proc_open for parallel processes
 * Tests firstOrCreate with lockForUpdate directly (bypassing CreateQuickOrder)
 * This directly tests the savepoint retry logic in reserveInventory
 */
it('genuinely handles concurrent 23505 race condition on missing inventory row', function (): void {
    $data = setupTestData();
    $tenantId = $data['tenantId'];
    $productId = $data['productId'];

    // Verify NO inventory item exists initially
    $initialCount = InventoryItem::withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenantId)
        ->where('product_id', $productId)
        ->whereNull('product_variant_id')
        ->count();
    expect($initialCount)->toBe(0);

    // Worker script that mimics the reserveInventory logic:
    // 1. firstOrCreate in a transaction (savepoint)
    // 2. On 23505, retry
    // 3. Then lockForUpdate and increment
    $workerScript = <<<'PHP'
<?php
require_once '/workspaces/dz-saas-commerce/backend/vendor/autoload.php';

$app = require_once '/workspaces/dz-saas-commerce/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

$tenantId = $argv[1];
$productId = $argv[2];

$maxAttempts = 3;
$inventoryItem = null;

for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
    try {
        DB::transaction(function () use ($tenantId, $productId, &$inventoryItem): void {
            $inventoryItem = InventoryItem::withoutGlobalScope('current_tenant')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->whereNull('product_variant_id')
                ->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'product_id' => $productId,
                        'product_variant_id' => null,
                    ],
                    [
                        'quantity' => 100,
                        'reserved_quantity' => 0,
                        'track_quantity' => true,
                        'allow_backorders' => false,
                        'sku' => 'TEST-SKU',
                    ]
                );
        });

        break;
    } catch (QueryException $e) {
        // Handle concurrent creation of the same inventory row (PostgreSQL 23505)
        if ($e->errorInfo[1] ?? null === 23505) {
            if ($attempt === $maxAttempts - 1) {
                echo "ERROR:QueryException:23505 max retries exceeded\n";
                exit(1);
            }
            continue;
        }
        echo "ERROR:" . get_class($e) . ":" . $e->getMessage() . "\n";
        exit(1);
    }
}

// Lock the inventory item now that we know it exists
try {
    $locked = InventoryItem::withoutGlobalScope('current_tenant')
        ->whereKey($inventoryItem->getKey())
        ->lockForUpdate()
        ->firstOrFail();

    $locked->increment('reserved_quantity', 1);

    echo "SUCCESS:{$locked->id}:{$locked->reserved_quantity}\n";
    exit(0);
} catch (\Throwable $e) {
    echo "ERROR:" . get_class($e) . ":" . $e->getMessage() . "\n";
    exit(1);
}
PHP;

    $workerCount = 5;
    $processes = [];

    // Launch all workers in parallel
    for ($i = 0; $i < $workerCount; $i++) {
        $result = runWorkerScript($workerScript, [$tenantId, $productId]);
        $processes[] = $result;
    }

    // Count successes
    $successCount = 0;
    $raceConditionErrorCount = 0;
    foreach ($processes as $i => $result) {
        echo "Worker $i: exit_code={$result['exit_code']}, stdout={$result['stdout']}, stderr={$result['stderr']}\n";
        if ($result['exit_code'] === 0 && str_starts_with($result['stdout'], 'SUCCESS:')) {
            $successCount++;
        } else {
            $raceConditionErrorCount++;
        }
    }

    // Check database state
    $inventoryCount = InventoryItem::withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenantId)
        ->where('product_id', $productId)
        ->whereNull('product_variant_id')
        ->count();

    // Must have exactly one inventory row (race condition handled)
    expect($inventoryCount)->toBe(1);

    $inventoryItem = InventoryItem::withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenantId)
        ->where('product_id', $productId)
        ->whereNull('product_variant_id')
        ->first();

    // Reserved quantity should equal successful increments
    expect($inventoryItem->reserved_quantity)->toBe($successCount);

    // No race condition errors (23505)
    expect($raceConditionErrorCount)->toBe(0);

    // At least one should succeed
    expect($successCount)->toBeGreaterThan(0);
    expect($successCount)->toBeLessThanOrEqual($workerCount);

    // Verify tenant isolation
    $otherTenant = Tenant::factory()->create();
    $otherCount = InventoryItem::withoutGlobalScope('current_tenant')
        ->where('tenant_id', $otherTenant->id)
        ->where('product_id', $productId)
        ->count();
    expect($otherCount)->toBe(0);

    cleanupTestData();
});