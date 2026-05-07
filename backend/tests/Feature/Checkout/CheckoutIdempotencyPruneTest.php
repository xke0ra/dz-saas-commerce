<?php

use App\Actions\Checkout\PruneCheckoutIdempotencyRecords;
use App\Models\CheckoutIdempotencyRecord;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

it('prunes expired checkout idempotency records and keeps active records', function (): void {
    $this->travelTo(now()->setDate(2026, 5, 7)->setTime(10, 0));

    $tenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create();

    $expired = CheckoutIdempotencyRecord::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'expires_at' => now()->subMinute(),
    ]);
    $active = CheckoutIdempotencyRecord::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'expires_at' => now()->addMinute(),
    ]);

    $deleted = app(PruneCheckoutIdempotencyRecords::class)->handle();

    expect($deleted)->toBe(1);

    $this->assertDatabaseMissing('checkout_idempotency_records', ['id' => $expired->id]);
    $this->assertDatabaseHas('checkout_idempotency_records', ['id' => $active->id]);
});

it('can report expired checkout idempotency records without deleting them', function (): void {
    $this->travelTo(now()->setDate(2026, 5, 7)->setTime(10, 0));

    $tenant = Tenant::factory()->create();
    $store = Store::factory()->for($tenant)->create();

    $expired = CheckoutIdempotencyRecord::factory()->create([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'expires_at' => now()->subMinute(),
    ]);

    $exitCode = Artisan::call('checkout-idempotency:prune', ['--dry-run' => true]);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('Found 1 expired checkout idempotency record(s).');

    $this->assertDatabaseHas('checkout_idempotency_records', ['id' => $expired->id]);
});
