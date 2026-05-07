<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreSetting>
 */
class StoreSettingFactory extends Factory
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
            'seller_name' => fake()->company(),
            'seller_address' => fake()->address(),
            'commercial_registration_number' => fake()->bothify('??-#######'),
            'tax_identification_number' => fake()->numerify('###############'),
            'public_email' => fake()->companyEmail(),
            'public_phone' => '0555123456',
            'support_phone' => '0555123456',
            'whatsapp_phone' => '0555123456',
            'seo_title' => $store->name,
            'seo_description' => fake()->sentence(),
            'announcement_text' => fake()->sentence(),
            'terms_content' => fake()->paragraphs(2, true),
            'privacy_content' => fake()->paragraphs(2, true),
            'return_policy_content' => fake()->paragraphs(2, true),
            'shipping_policy_content' => fake()->paragraphs(2, true),
            'social_links' => [],
            'metadata' => [],
        ];
    }

    public function forStore(Store $store): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'seo_title' => $store->name,
        ]);
    }
}
