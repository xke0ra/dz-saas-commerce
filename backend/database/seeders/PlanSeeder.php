<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed commercial SaaS plans and their first feature limits.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price_minor' => 250000,
                'sort_order' => 10,
                'features' => [
                    'max_products' => 100,
                    'max_orders_per_month' => 500,
                    'max_staff_users' => 2,
                    'max_images_per_product' => 4,
                    'custom_domain' => false,
                    'advanced_analytics' => false,
                    'coupons' => false,
                    'abandoned_cart' => false,
                    'api_access' => false,
                    'multi_warehouse' => false,
                    'premium_themes' => false,
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_minor' => 590000,
                'sort_order' => 20,
                'features' => [
                    'max_products' => 1000,
                    'max_orders_per_month' => 3000,
                    'max_staff_users' => 5,
                    'max_images_per_product' => 8,
                    'custom_domain' => true,
                    'advanced_analytics' => true,
                    'coupons' => true,
                    'abandoned_cart' => false,
                    'api_access' => false,
                    'multi_warehouse' => false,
                    'premium_themes' => true,
                ],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'price_minor' => 1290000,
                'sort_order' => 30,
                'features' => [
                    'max_products' => 10000,
                    'max_orders_per_month' => 15000,
                    'max_staff_users' => 20,
                    'max_images_per_product' => 12,
                    'custom_domain' => true,
                    'advanced_analytics' => true,
                    'coupons' => true,
                    'abandoned_cart' => true,
                    'api_access' => true,
                    'multi_warehouse' => true,
                    'premium_themes' => true,
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price_minor' => 0,
                'sort_order' => 40,
                'features' => [
                    'max_products' => null,
                    'max_orders_per_month' => null,
                    'max_staff_users' => null,
                    'max_images_per_product' => 20,
                    'custom_domain' => true,
                    'advanced_analytics' => true,
                    'coupons' => true,
                    'abandoned_cart' => true,
                    'api_access' => true,
                    'multi_warehouse' => true,
                    'premium_themes' => true,
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $features = $planData['features'];
            unset($planData['features']);

            $plan = Plan::query()->updateOrCreate(
                ['slug' => $planData['slug']],
                [
                    ...$planData,
                    'currency' => 'DZD',
                    'billing_interval' => 'monthly',
                    'is_active' => true,
                    'metadata' => [],
                ],
            );

            foreach ($features as $key => $value) {
                PlanFeature::query()->updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'key' => $key,
                    ],
                    ['value' => ['value' => $value]],
                );
            }
        }
    }
}
