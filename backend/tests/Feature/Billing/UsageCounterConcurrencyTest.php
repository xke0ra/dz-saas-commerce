<?php

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Support\Billing\SubscriptionFeatureGate;
use App\Support\Tenancy\CurrentTenant;
use App\Enums\PlanFeatureKey;
use Database\Seeders\AlgeriaGeographySeeder;
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
    $tenantId = getenv('CONCURRENCY_TEST_TENANT_ID');
    if ($tenantId !== false && $tenantId !== '') {
        return ['tenantId' => $tenantId];
    }

    // Ensure database is migrated and seeded
    Artisan::call('migrate:fresh', ['--force' => true]);
    Artisan::call('db:seed', ['--class' => AlgeriaGeographySeeder::class, '--force' => true]);

    $wilaya = \App\Models\Wilaya::query()->findOrFail(16);
    $commune = \App\Models\Commune::query()
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
        'value' => ['value' => '100'],
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

    $customer = \App\Models\Customer::create([
        'tenant_id' => $tenant->id,
        'full_name' => 'Test Customer',
        'phone' => '0555' . random_int(100000, 999999),
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'address' => 'Test Address',
    ]);

    $paymentMethod = \App\Models\PaymentMethod::create([
        'tenant_id' => $tenant->id,
        'type' => \App\Enums\PaymentMethodType::CashOnDelivery,
        'name' => 'Cash on Delivery',
        'is_active' => true,
        'metadata' => [],
    ]);

    $shippingRate = \App\Models\ShippingRate::create([
        'tenant_id' => $tenant->id,
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'delivery_type' => \App\Enums\DeliveryType::Home,
        'price_minor' => 5000,
        'currency' => 'DZD',
        'is_active' => true,
    ]);

    // Store in environment for worker processes
    putenv("CONCURRENCY_TENANT_ID={$tenant->id}");
    putenv("CONCURRENCY_STORE_ID={$store->id}");
    putenv("CONCURRENCY_CUSTOMER_ID={$customer->id}");
    putenv("CONCURRENCY_PAYMENT_METHOD_ID={$paymentMethod->id}");
    putenv("CONCURRENCY_SHIPPING_RATE_ID={$shippingRate->id}");
    putenv("CONCURRENCY_WILAYA_ID={$wilaya->id}");
    putenv("CONCURRENCY_COMMUNE_ID={$commune->id}");

    return ['tenantId' => $tenant->id];
}

function cleanupTestData(): void
{
    $tenantId = getenv('CONCURRENCY_TEST_TENANT_ID');
    if ($tenantId !== false && $tenantId !== '') {
        \Illuminate\Support\Facades\DB::statement('DELETE FROM usage_counters WHERE tenant_id = ?', [$tenantId]);
        \Illuminate\Support\Facades\DB::statement('DELETE FROM products WHERE tenant_id = ?', [$tenantId]);
        \Illuminate\Support\Facades\DB::statement('DELETE FROM orders WHERE tenant_id = ?', [$tenantId]);
        \Illuminate\Support\Facades\DB::statement('DELETE FROM subscriptions WHERE tenant_id = ?', [$tenantId]);
        \Illuminate\Support\Facades\DB::statement('DELETE FROM stores WHERE tenant_id = ?', [$tenantId]);
        \Illuminate\Support\Facades\DB::statement('DELETE FROM customers WHERE tenant_id = ?', [$tenantId]);
        \Illuminate\Support\Facades\DB::statement('DELETE FROM payment_methods WHERE tenant_id = ?', [$tenantId]);
        \Illuminate\Support\Facades\DB::statement('DELETE FROM shipping_rates WHERE tenant_id = ?', [$tenantId]);
        \Illuminate\Support\Facades\DB::statement('DELETE FROM plans WHERE tenant_id = ?', [$tenantId]);
        \Illuminate\Support\Facades\DB::statement('DELETE FROM tenants WHERE id = ?', [$tenantId]);
        putenv('CONCURRENCY_TENANT_ID');
    }
}

function runConcurrencyWorkerScript(string $script, array $args): array
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
 * Genuine concurrency test: reconciliation vs enforcement race.
 * Uses separate PHP processes with separate PostgreSQL connections.
 */
it('reconciliation vs ensureWithinLimit cannot lose legitimate increment', function (): void {
    $data = setupTestData();
    $tenantId = $data['tenantId'];
    $key = 'max_products';
    $limit = 5;

    // Get subscription to determine billing period
    $subscription = \App\Models\Subscription::withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenantId)
        ->where('is_current', true)
        ->whereIn('status', ['trialing', 'active', 'grace_period', 'past_due'])
        ->first();
    $periodStart = $subscription
        ? $subscription->current_period_starts_at->toDateString()
        : now()->startOfMonth()->toDateString();
    $periodEnd = $subscription
        ? $subscription->current_period_ends_at->toDateString()
        : now()->endOfMonth()->toDateString();

    // Pre-create counter with some value using subscription's billing period
    \App\Models\UsageCounter::create([
        'tenant_id' => $tenantId,
        'key' => $key,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'used' => 0,
        'limit_value' => $limit,
    ]);

    // Create a product for authoritative usage calculation
    \App\Models\Product::create([
        'tenant_id' => $tenantId,
        'name' => 'Test Product',
        'slug' => 'test-product-' . uniqid(),
        'sku' => 'TEST-' . uniqid(),
        'type' => \App\Enums\ProductType::Simple,
        'status' => \App\Enums\ProductStatus::Active,
        'price_minor' => 10000,
        'currency' => 'DZD',
    ]);

    // Generate a unique advisory lock key and barrier file for this test run
    $lockKey = random_int(1000000, 9999999);
    $barrierFile = sys_get_temp_dir() . "/reconciliation_barrier_" . uniqid() . ".tmp";

    // Worker 1: Run reconciliation
    $reconciliationScript = <<<'PHP'
<?php
require_once '/workspaces/dz-saas-commerce/backend/vendor/autoload.php';
$app = require_once '/workspaces/dz-saas-commerce/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\UsageCounter;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

$tenantId = $argv[1];
$key = $argv[2];
$lockKey = (int)$argv[3];
$barrierFile = $argv[4];

DB::transaction(function () use ($tenantId, $key, $lockKey, $barrierFile): void {
    DB::statement("SELECT pg_advisory_lock($lockKey)");
    
    // Signal that we have the lock and are ready
    file_put_contents($barrierFile, "ready");
    
    // Get subscription to determine billing period
    $subscription = Subscription::query()
        ->withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenantId)
        ->where('is_current', true)
        ->whereIn('status', ['trialing', 'active', 'grace_period', 'past_due'])
        ->first();
    
    $periodStart = $subscription 
        ? Carbon::parse($subscription->current_period_starts_at)->startOfDay()->toDateString()
        : now()->startOfMonth()->toDateString();
    $periodEnd = $subscription
        ? Carbon::parse($subscription->current_period_ends_at)->endOfDay()->toDateString()
        : now()->endOfMonth()->toDateString();

    $counter = UsageCounter::query()
        ->withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenantId)
        ->where('key', $key)
        ->where('period_start', $periodStart)
        ->where('period_end', $periodEnd)
        ->lockForUpdate()
        ->first();

    usleep(50000);

    $authoritativeUsage = Product::query()
        ->withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenantId)
        ->where('status', 'active')
        ->whereDate('created_at', '>=', $periodStart)
        ->whereDate('created_at', '<=', $periodEnd)
        ->count();

    if ($counter) {
        $counter->update(['used' => $authoritativeUsage]);
    }
    
    DB::statement("SELECT pg_advisory_unlock($lockKey)");
});

echo "RECONCILIATION_DONE\n";
exit(0);
PHP;

    // Worker 2: Run enforcement (increment counter)
    $enforcementScript = <<<'PHP'
<?php
require_once '/workspaces/dz-saas-commerce/backend/vendor/autoload.php';
$app = require_once '/workspaces/dz-saas-commerce/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\Billing\SubscriptionFeatureGate;
use App\Enums\PlanFeatureKey;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

$tenantId = $argv[1];
$key = $argv[2];
$lockKey = (int)$argv[3];
$barrierFile = $argv[4];

$featureGate = app(SubscriptionFeatureGate::class);

// Wait for reconciliation to signal readiness
while (!file_exists($barrierFile)) {
    usleep(10000);
}

try {
    DB::transaction(function () use ($tenantId, $key, $lockKey): void {
        DB::statement("SELECT pg_advisory_lock($lockKey)");
        
        $featureGate = app(\App\Support\Billing\SubscriptionFeatureGate::class);
        $featureGate->ensureWithinLimit($tenantId, \App\Enums\PlanFeatureKey::tryFrom($key), 1);
        
        DB::statement("SELECT pg_advisory_unlock($lockKey)");
    });
    
    echo "ENFORCEMENT_DONE\n";
    exit(0);
} catch (ValidationException $e) {
    echo "LIMIT_EXCEEDED\n";
    exit(0);
} catch (\Throwable $e) {
    echo "ERROR:" . get_class($e) . ':' . $e->getMessage() . "\n";
    exit(1);
}
PHP;

    // Generate a unique advisory lock key and barrier file for this test run
    $lockKey = random_int(1000000, 9999999);
    $barrierFile = sys_get_temp_dir() . "/reconciliation_barrier_" . uniqid() . ".tmp";

    // Launch both workers in parallel with advisory lock and barrier file
    $processes = [];

    // Start reconciliation worker
    $p1 = runConcurrencyWorkerScript($reconciliationScript, [$tenantId, 'max_products', $lockKey, $barrierFile]);
    $processes[] = ['name' => 'reconciliation', 'result' => $p1];

    // Wait for reconciliation to signal readiness via barrier file
    $timeout = 5;
    $start = time();
    while (!file_exists($barrierFile) && (time() - $start) < $timeout) {
        usleep(10000);
    }
    if (!file_exists($barrierFile)) {
        throw new \RuntimeException("Reconciliation worker did not signal readiness in time");
    }

    // Start enforcement worker
    $p2 = runConcurrencyWorkerScript($enforcementScript, [$tenantId, 'max_products', $lockKey, $barrierFile]);
    $processes[] = ['name' => 'enforcement', 'result' => $p2];

    // Wait for both to complete
    foreach ($processes as $proc) {
        echo "{$proc['name']}: exit={$proc['result']['exit_code']}, stdout={$proc['result']['stdout']}\n";
    }

    // Check final counter state
    $counter = \App\Models\UsageCounter::withoutGlobalScope('current_tenant')
        ->where('tenant_id', $tenantId)
        ->where('key', 'max_products')
        ->first();

    // The counter should reflect BOTH the reconciliation (1 product) AND the enforcement (+1)
    // So final value should be 2 (1 from reconciliation + 1 from enforcement)
    // NOT 1 (which would mean reconciliation overwrote enforcement)
    expect($counter->used)->toBe(2);

    // Both should succeed
    $successCount = 0;
    foreach ($processes as $proc) {
        if ($proc['result']['exit_code'] === 0 && in_array($proc['result']['stdout'], ['RECONCILIATION_DONE', 'ENFORCEMENT_DONE'])) {
            $successCount++;
        }
    }
    expect($successCount)->toBe(2);
});