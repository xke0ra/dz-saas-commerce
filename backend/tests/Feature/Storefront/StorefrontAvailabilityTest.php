<?php

use App\Enums\StoreStatus;
use App\Enums\TenantStatus;
use App\Models\Store;
use App\Models\StoreSetting;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use Database\Seeders\AlgeriaGeographySeeder;

it('resolves active public stores for active tenants', function (): void {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    Store::factory()->for($tenant)->create([
        'subdomain' => 'public-active',
        'status' => StoreStatus::Active,
    ]);

    $response = $this->getJson('/api/storefront/resolve?host=public-active.platform.test');

    $response
        ->assertOk()
        ->assertJsonMissingPath('data.tenant_id')
        ->assertJsonPath('data.subdomain', 'public-active')
        ->assertJsonPath('data.status', StoreStatus::Active->value);
});

it('does not resolve draft stores from the public storefront API', function (): void {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    Store::factory()->for($tenant)->create([
        'subdomain' => 'draft-store',
        'status' => StoreStatus::Draft,
    ]);

    $this->getJson('/api/storefront/resolve?host=draft-store.platform.test')
        ->assertNotFound();
});

it('does not serve suspended stores from storefront routes', function (): void {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    $store = Store::factory()->for($tenant)->create([
        'subdomain' => 'suspended-store',
        'status' => StoreStatus::Suspended,
    ]);

    $this->getJson("/api/storefront/{$store->subdomain}/home")
        ->assertNotFound();
});

it('does not serve active stores when the tenant is suspended', function (): void {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Suspended]);
    $store = Store::factory()->for($tenant)->create([
        'subdomain' => 'suspended-tenant',
        'status' => StoreStatus::Active,
    ]);

    $this->getJson("/api/storefront/{$store->subdomain}/home")
        ->assertNotFound();
});

it('exposes active Algerian wilayas and communes for quick checkout forms', function (): void {
    $this->seed(AlgeriaGeographySeeder::class);

    $this->getJson('/api/storefront/geography/wilayas')
        ->assertOk()
        ->assertJsonCount(58, 'data')
        ->assertJsonPath('data.0.id', 1)
        ->assertJsonPath('data.15.id', 16)
        ->assertJsonPath('data.15.name_fr', 'Alger');

    $this->getJson('/api/storefront/geography/communes?wilaya_id=16')
        ->assertOk()
        ->assertJsonCount(57, 'data')
        ->assertJsonFragment([
            'wilaya_id' => 16,
            'name_fr' => 'Alger Centre',
            'postal_code' => '16001',
        ]);
});

it('exposes storefront legal and theme settings for active stores', function (): void {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    $store = Store::factory()->for($tenant)->create([
        'slug' => 'settings-store',
        'subdomain' => 'settings-store',
        'status' => StoreStatus::Active,
    ]);

    StoreSetting::factory()->forStore($store)->create([
        'seo_title' => 'Custom SEO title',
        'announcement_text' => 'Free delivery this week.',
        'terms_content' => 'Store terms content.',
    ]);
    ThemeSetting::factory()->forStore($store)->create([
        'primary_color' => '#123456',
        'accent_color' => '#abcdef',
        'hero_title' => 'Custom hero title',
    ]);

    $this->getJson("/api/storefront/{$store->slug}/home")
        ->assertOk()
        ->assertJsonMissingPath('store.tenant_id')
        ->assertJsonPath('store.store_setting.seo_title', 'Custom SEO title')
        ->assertJsonPath('store.store_setting.announcement_text', 'Free delivery this week.')
        ->assertJsonPath('store.store_setting.legal_pages.terms', true)
        ->assertJsonPath('store.theme_setting.primary_color', '#123456')
        ->assertJsonPath('store.theme_setting.accent_color', '#abcdef')
        ->assertJsonPath('store.theme_setting.hero_title', 'Custom hero title');
});
