<?php

namespace Database\Seeders;

use App\Actions\Billing\StartTenantSubscription;
use App\Enums\CategoryStatus;
use App\Enums\CouponType;
use App\Enums\DeliveryType;
use App\Enums\PaymentMethodType;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\TenantStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\InventoryItem;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Models\StoreSetting;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use App\Models\Wilaya;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class StorefrontDemoSeeder extends Seeder
{
    /**
     * Seed one local demo storefront for frontend development.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            AlgeriaGeographySeeder::class,
        ]);

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'demo-tenant'],
            [
                'name' => 'Demo Tenant',
                'status' => TenantStatus::Active,
                'owner_id' => null,
                'settings' => [],
            ],
        );

        $store = Store::query()->updateOrCreate(
            ['slug' => 'demo-store'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'متجر تجريبي',
                'domain' => null,
                'subdomain' => 'demo',
                'status' => StoreStatus::Active,
                'locale' => 'ar',
                'currency' => 'DZD',
                'settings' => [],
            ],
        );
        $heroImagePath = $this->generateDemoPng(
            path: 'storefront/demo/hero.png',
            title: 'DZ SaaS Commerce',
            palette: ['#107062', '#b54836', '#f7f9fa'],
            width: 1600,
            height: 900,
        );

        StoreSetting::query()
            ->withoutGlobalScope('current_tenant')
            ->updateOrCreate(
                ['store_id' => $store->id],
                [
                    'tenant_id' => $tenant->id,
                    'seller_name' => 'Demo Commerce DZ',
                    'seller_address' => 'Alger Centre, Alger',
                    'commercial_registration_number' => '16-1234567',
                    'tax_identification_number' => '000000000000001',
                    'public_email' => 'contact@demo-store.dz',
                    'public_phone' => '0555123456',
                    'support_phone' => '0555123456',
                    'whatsapp_phone' => '0555123456',
                    'seo_title' => 'متجر تجريبي للتجارة الإلكترونية في الجزائر',
                    'seo_description' => 'واجهة تجريبية لمنصة dz-saas-commerce مع طلب سريع ودفع عند الاستلام.',
                    'announcement_text' => 'الدفع عند الاستلام متاح مع تأكيد هاتفي قبل الشحن.',
                    'terms_content' => 'هذه شروط استخدام تجريبية للمتجر. يتم تأكيد كل الطلبات هاتفيا قبل الإرسال.',
                    'privacy_content' => 'تستعمل بيانات العميل فقط لمعالجة الطلب والتواصل حول الشحن.',
                    'return_policy_content' => 'يمكن طلب الإرجاع حسب حالة المنتج ومدة الإرجاع التي يحددها التاجر.',
                    'shipping_policy_content' => 'تختلف أسعار التوصيل حسب الولاية ونوع التوصيل إلى المنزل أو المكتب.',
                    'social_links' => [
                        'facebook' => 'https://facebook.com/demo-store',
                        'instagram' => 'https://instagram.com/demo-store',
                    ],
                    'metadata' => [],
                ],
            );

        ThemeSetting::query()
            ->withoutGlobalScope('current_tenant')
            ->updateOrCreate(
                ['store_id' => $store->id],
                [
                    'tenant_id' => $tenant->id,
                    'theme_name' => 'default',
                    'primary_color' => '#107062',
                    'accent_color' => '#b54836',
                    'background_color' => '#f7f9fa',
                    'foreground_color' => '#161c24',
                    'heading_font' => null,
                    'body_font' => null,
                    'logo_path' => null,
                    'favicon_path' => null,
                    'hero_image_path' => $heroImagePath,
                    'hero_title' => 'متجر تجريبي للتجارة داخل الجزائر',
                    'hero_subtitle' => 'منتجات مختارة، طلب سريع، دفع عند الاستلام، وتوصيل حسب الولاية والبلدية.',
                    'product_card_style' => 'standard',
                    'layout_settings' => [],
                    'is_active' => true,
                ],
            );

        $categories = collect([
            ['name' => 'إلكترونيات', 'slug' => 'electronics', 'description' => 'منتجات تقنية للاستخدام اليومي.'],
            ['name' => 'المنزل', 'slug' => 'home', 'description' => 'لوازم عملية للبيت والمكتب.'],
            ['name' => 'العناية', 'slug' => 'care', 'description' => 'منتجات عناية شخصية مختارة.'],
        ])->mapWithKeys(function (array $categoryData) use ($tenant): array {
            $category = Category::query()
                ->withoutGlobalScope('current_tenant')
                ->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'slug' => $categoryData['slug'],
                    ],
                    [
                        'name' => $categoryData['name'],
                        'description' => $categoryData['description'],
                        'status' => CategoryStatus::Active,
                        'sort_order' => 10,
                        'metadata' => [],
                    ],
                );

            return [$categoryData['slug'] => $category];
        });

        $products = [
            [
                'category' => 'electronics',
                'name' => 'سماعات لاسلكية',
                'slug' => 'wireless-earbuds',
                'image_title' => 'Wireless Earbuds',
                'image_palette' => ['#0f766e', '#22c55e', '#ecfeff'],
                'sku' => 'DEMO-EARBUDS',
                'short_description' => 'سماعات خفيفة مع علبة شحن ومكالمات واضحة.',
                'description' => 'مناسبة للرياضة والعمل اليومي. يتم تأكيد الطلب هاتفيا قبل الشحن.',
                'price_minor' => 390000,
                'compare_at_price_minor' => 490000,
                'is_featured' => true,
            ],
            [
                'category' => 'electronics',
                'name' => 'شاحن سريع USB-C',
                'slug' => 'fast-usb-c-charger',
                'image_title' => 'USB-C Charger',
                'image_palette' => ['#1d4ed8', '#38bdf8', '#eff6ff'],
                'sku' => 'DEMO-CHARGER',
                'short_description' => 'شاحن سريع متوافق مع أغلب الهواتف الحديثة.',
                'description' => 'ضمان تجربة طلب سريع مع الدفع عند الاستلام.',
                'price_minor' => 180000,
                'compare_at_price_minor' => null,
                'is_featured' => true,
            ],
            [
                'category' => 'home',
                'name' => 'مصباح مكتب LED',
                'slug' => 'led-desk-lamp',
                'image_title' => 'LED Desk Lamp',
                'image_palette' => ['#92400e', '#f59e0b', '#fffbeb'],
                'sku' => 'DEMO-LAMP',
                'short_description' => 'إضاءة مريحة بثلاث درجات للقراءة والعمل.',
                'description' => 'منتج تجريبي لإظهار تفاصيل المنتج ونموذج الطلب.',
                'price_minor' => 260000,
                'compare_at_price_minor' => 320000,
                'is_featured' => true,
            ],
            [
                'category' => 'care',
                'name' => 'مجموعة عناية يومية',
                'slug' => 'daily-care-kit',
                'image_title' => 'Daily Care Kit',
                'image_palette' => ['#be123c', '#fb7185', '#fff1f2'],
                'sku' => 'DEMO-CARE',
                'short_description' => 'مجموعة مختصرة للاستخدام اليومي.',
                'description' => 'يمكن طلبها عبر النموذج السريع بدون إنشاء حساب.',
                'price_minor' => 210000,
                'compare_at_price_minor' => null,
                'is_featured' => false,
            ],
        ];

        foreach ($products as $index => $productData) {
            $product = Product::query()
                ->withoutGlobalScope('current_tenant')
                ->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'slug' => $productData['slug'],
                    ],
                    [
                        'category_id' => $categories[$productData['category']]->id,
                        'name' => $productData['name'],
                        'sku' => $productData['sku'],
                        'short_description' => $productData['short_description'],
                        'description' => $productData['description'],
                        'status' => ProductStatus::Active,
                        'price_minor' => $productData['price_minor'],
                        'compare_at_price_minor' => $productData['compare_at_price_minor'],
                        'cost_price_minor' => null,
                        'currency' => 'DZD',
                        'requires_shipping' => true,
                        'is_featured' => $productData['is_featured'],
                        'sort_order' => $index + 1,
                        'published_at' => now()->subMinute(),
                        'metadata' => [],
                    ],
                );

            InventoryItem::query()
                ->withoutGlobalScope('current_tenant')
                ->updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'tenant_id' => $tenant->id,
                        'sku' => $product->sku,
                        'quantity' => 80,
                        'reserved_quantity' => 0,
                        'low_stock_threshold' => 10,
                        'track_quantity' => true,
                        'allow_backorders' => false,
                    ],
                );

            $imagePath = $this->generateDemoPng(
                path: "tenant-products/demo/{$productData['slug']}.png",
                title: $productData['image_title'],
                palette: $productData['image_palette'],
            );

            if ($imagePath !== null) {
                ProductImage::query()
                    ->withoutGlobalScope('current_tenant')
                    ->updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'product_id' => $product->id,
                            'path' => $imagePath,
                        ],
                        [
                            'alt' => $product->name,
                            'sort_order' => 0,
                            'is_primary' => true,
                            'metadata' => [],
                        ],
                    );
            }
        }

        PaymentMethod::query()
            ->withoutGlobalScope('current_tenant')
            ->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'type' => PaymentMethodType::CashOnDelivery,
                ],
                [
                    'name' => 'الدفع عند الاستلام',
                    'is_active' => true,
                    'instructions' => 'يتم الدفع نقدا عند استلام الطلب.',
                    'settings' => [],
                ],
            );

        Coupon::query()
            ->withoutGlobalScope('current_tenant')
            ->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'code' => 'DEMO10',
                ],
                [
                    'name' => 'خصم تجريبي 10%',
                    'type' => CouponType::Percentage,
                    'value' => 10,
                    'max_discount_minor' => 50000,
                    'minimum_subtotal_minor' => 0,
                    'usage_limit' => null,
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addYear(),
                    'is_active' => true,
                    'metadata' => [],
                ],
            );

        Wilaya::query()
            ->orderBy('id')
            ->each(function (Wilaya $wilaya) use ($tenant): void {
                foreach ([DeliveryType::Home, DeliveryType::Desk] as $deliveryType) {
                    ShippingRate::query()
                        ->withoutGlobalScope('current_tenant')
                        ->updateOrCreate(
                            [
                                'tenant_id' => $tenant->id,
                                'wilaya_id' => $wilaya->id,
                                'commune_id' => null,
                                'delivery_type' => $deliveryType,
                            ],
                            [
                                'price_minor' => $deliveryType === DeliveryType::Home
                                    ? 55000 + ($wilaya->id * 1000)
                                    : 35000 + ($wilaya->id * 800),
                                'currency' => 'DZD',
                                'is_active' => true,
                            ],
                        );
                }
            });

        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        if (! $tenant->subscriptions()->where('is_current', true)->exists()) {
            app(StartTenantSubscription::class)->handle(
                tenant: $tenant,
                plan: $plan,
                createInvoice: false,
            );
        }

        $this->command?->info('Demo storefront seeded. Use NEXT_PUBLIC_DEFAULT_STORE=demo-store.');
    }

    /**
     * @param  array<int, string>  $palette
     */
    private function generateDemoPng(string $path, string $title, array $palette, int $width = 1200, int $height = 900): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            return null;
        }

        [$primary, $accent, $background] = array_pad($palette, 3, '#f7f9fa');
        $backgroundColor = $this->allocateHexColor($image, $background);
        $primaryColor = $this->allocateHexColor($image, $primary);
        $accentColor = $this->allocateHexColor($image, $accent);
        $white = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, $width, $height, $backgroundColor);
        imagefilledellipse($image, (int) ($width * 0.78), (int) ($height * 0.24), (int) ($width * 0.46), (int) ($height * 0.46), $primaryColor);
        imagefilledellipse($image, (int) ($width * 0.24), (int) ($height * 0.74), (int) ($width * 0.36), (int) ($height * 0.36), $accentColor);
        imagefilledrectangle($image, (int) ($width * 0.16), (int) ($height * 0.22), (int) ($width * 0.84), (int) ($height * 0.70), $white);
        imagefilledrectangle($image, (int) ($width * 0.20), (int) ($height * 0.27), (int) ($width * 0.80), (int) ($height * 0.64), $primaryColor);

        imagestring($image, 5, (int) ($width * 0.24), (int) ($height * 0.45), $title, $white);
        imagestring($image, 3, (int) ($width * 0.24), (int) ($height * 0.52), 'Demo storefront asset', $white);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if ($png === false) {
            return null;
        }

        Storage::disk('public')->put($path, $png);

        return $path;
    }

    private function allocateHexColor(\GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        if (! preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            $hex = 'f7f9fa';
        }

        return imagecolorallocate(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }
}
