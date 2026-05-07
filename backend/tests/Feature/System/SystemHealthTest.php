<?php

use Illuminate\Support\Facades\Artisan;

it('reports liveness from the public health endpoint', function (): void {
    $response = $this->getJson('/api/system/health/live');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('scope', 'live')
        ->assertJsonPath('checks.0.name', 'app')
        ->assertJsonPath('checks.0.status', 'ok');
});

it('reports readiness from the public health endpoint', function (): void {
    $response = $this->getJson('/api/system/health/ready');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('scope', 'ready');

    $checks = collect($response->json('checks'))->pluck('status', 'name');

    expect($checks->get('database'))->toBe('ok')
        ->and($checks->get('cache'))->toBe('ok')
        ->and($checks->get('queue'))->toBe('ok')
        ->and($checks->get('storage'))->toBe('ok')
        ->and($checks->get('redis'))->toBe('skipped')
        ->and($checks->get('search'))->toBe('skipped');
});

it('runs system health as a json artisan smoke command', function (): void {
    $exitCode = Artisan::call('system:health', [
        '--scope' => 'ready',
        '--format' => 'json',
    ]);

    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('ok')
        ->and($payload['scope'])->toBe('ready')
        ->and(collect($payload['checks'])->pluck('name')->all())->toContain('database', 'cache', 'queue', 'storage');
});
