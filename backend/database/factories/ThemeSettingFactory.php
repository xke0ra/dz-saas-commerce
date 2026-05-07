<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\ThemeSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThemeSetting>
 */
class ThemeSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory()->create();

        return [
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'theme_name' => 'default',
            'primary_color' => '#107062',
            'accent_color' => '#b54836',
            'background_color' => '#f7f9fa',
            'foreground_color' => '#161c24',
            'heading_font' => null,
            'body_font' => null,
            'logo_path' => null,
            'favicon_path' => null,
            'hero_image_path' => null,
            'hero_title' => $store->name,
            'hero_subtitle' => fake()->sentence(),
            'product_card_style' => 'standard',
            'layout_settings' => [],
            'is_active' => true,
        ];
    }

    public function forStore(Store $store): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'hero_title' => $store->name,
        ]);
    }
}
