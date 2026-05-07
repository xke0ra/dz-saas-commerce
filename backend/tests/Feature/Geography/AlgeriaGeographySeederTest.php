<?php

use App\Models\Commune;
use App\Models\Wilaya;
use Database\Seeders\AlgeriaGeographySeeder;

it('seeds the complete 58 wilaya and 1541 commune checkout dataset', function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    expect(Wilaya::query()->where('is_active', true)->count())->toBe(58)
        ->and(Commune::query()->where('is_active', true)->count())->toBe(1541);
});

it('seeds known Algerian communes with their wilaya and postal codes', function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    expect(Commune::query()->where('wilaya_id', 16)->where('name_fr', 'Alger Centre')->first())
        ->not->toBeNull()
        ->postal_code->toBe('16001')
        ->and(Commune::query()->where('wilaya_id', 31)->where('name_fr', 'Oran')->first())
        ->not->toBeNull()
        ->postal_code->toBe('31001')
        ->and(Commune::query()->where('wilaya_id', 49)->where('name_fr', 'Timimoun')->first())
        ->not->toBeNull();
});

it('keeps the geography API scoped to active communes for the selected wilaya', function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->getJson('/api/storefront/geography/communes?wilaya_id=16')
        ->assertOk()
        ->assertJsonCount(57, 'data')
        ->assertJsonFragment([
            'wilaya_id' => 16,
            'name_fr' => 'Alger Centre',
            'postal_code' => '16001',
        ])
        ->assertJsonMissing([
            'wilaya_id' => 31,
            'name_fr' => 'Oran',
        ]);
});
