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

    expect($checks->get('environment'))->toBe('ok')
        ->and($checks->get('database'))->toBe('ok')
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
        ->and(collect($payload['checks'])->pluck('name')->all())->toContain('environment', 'database', 'cache', 'queue', 'storage');
});

it('fails readiness when production debug mode is enabled', function (): void {
    config([
        'app.env' => 'production',
        'app.debug' => true,
        'app.key' => 'base64:test-production-key',
    ]);

    $response = $this->getJson('/api/system/health/ready');

    $response
        ->assertStatus(503)
        ->assertJsonPath('status', 'failed');

    $checks = collect($response->json('checks'))->pluck('status', 'name');

    expect($checks->get('environment'))->toBe('failed');
});

it('fails readiness when the production app key is missing', function (): void {
    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => null,
    ]);

    $response = $this->getJson('/api/system/health/ready');

    $response
        ->assertStatus(503)
        ->assertJsonPath('status', 'failed');

    $checks = collect($response->json('checks'))->pluck('status', 'name');

    expect($checks->get('environment'))->toBe('failed');
});
