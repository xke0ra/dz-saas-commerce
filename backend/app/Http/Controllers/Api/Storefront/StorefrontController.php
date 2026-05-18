<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Actions\Catalog\SearchStorefrontProducts;
use App\Actions\Checkout\CreateQuickOrder;
use App\Data\Checkout\CheckoutOrderResult;
use App\Data\Checkout\QuickOrderData;
use App\Enums\CategoryStatus;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\QuickCheckoutRequest;
use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\OrderResource;
use App\Http\Resources\Storefront\ProductResource;
use App\Http\Resources\Storefront\StoreResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Support\Checkout\CheckoutAbuseGuard;
use App\Support\Checkout\CheckoutIdempotency;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
        private readonly CheckoutAbuseGuard $checkoutAbuseGuard,
        private readonly CheckoutIdempotency $checkoutIdempotency,
    ) {}

    public function resolve(Request $request): JsonResource
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255'],
        ]);

        $store = $this->tenantResolver->resolveStoreFromHost($validated['host']);

        abort_if($store === null, 404, 'Store not found.');
        $this->abortIfStorefrontUnavailable($store);

        return StoreResource::make($store);
    }

    public function home(string $store): array
    {
        $storeModel = $this->resolveStore($store);

        $categories = $this->categoryQuery($storeModel)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get();

        $featuredProducts = $this->productQuery($storeModel)
            ->visibleOnStorefront()
            ->where('is_featured', true)
            ->with(['category', 'images', 'inventoryItem'])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        return [
            'store' => StoreResource::make($storeModel),
            'categories' => CategoryResource::collection($categories),
            'featured_products' => ProductResource::collection($featuredProducts),
        ];
    }

    public function products(Request $request, string $store): AnonymousResourceCollection
    {
        $storeModel = $this->resolveStore($store);
        $categorySlug = $request->query('category');

        $products = $this->productQuery($storeModel)
            ->visibleOnStorefront()
            ->when(is_string($categorySlug) && $categorySlug !== '', function (Builder $query) use ($storeModel, $categorySlug): void {
                $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery
                    ->withoutGlobalScope('current_tenant')
                    ->where('tenant_id', $storeModel->tenant_id)
                    ->where('slug', $categorySlug));
            })
            ->with(['category', 'images', 'inventoryItem'])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->query('per_page', 24), 48));

        return ProductResource::collection($products);
    }

    public function product(string $store, string $slug): ProductResource
    {
        $storeModel = $this->resolveStore($store);

        $product = $this->productQuery($storeModel)
            ->visibleOnStorefront()
            ->where('slug', $slug)
            ->with([
                'category',
                'images',
                'inventoryItem',
                'options.values',
                'variants' => fn ($query) => $query
                    ->where('status', ProductStatus::Active->value)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'variants.inventoryItems',
                'variants.optionValues.option',
            ])
            ->firstOrFail();

        return ProductResource::make($product);
    }

    public function categories(string $store): AnonymousResourceCollection
    {
        $storeModel = $this->resolveStore($store);

        $categories = $this->categoryQuery($storeModel)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function category(string $store, string $slug): CategoryResource
    {
        $storeModel = $this->resolveStore($store);

        $category = $this->categoryQuery($storeModel)
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        return CategoryResource::make($category);
    }

    public function search(Request $request, string $store, SearchStorefrontProducts $searchStorefrontProducts): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $storeModel = $this->resolveStore($store);
        $products = $searchStorefrontProducts->handle(
            store: $storeModel,
            term: $validated['q'],
            perPage: (int) $request->query('per_page', 24),
        );

        return ProductResource::collection($products);
    }

    public function checkout(QuickCheckoutRequest $request, string $store, CreateQuickOrder $createQuickOrder): JsonResponse
    {
        $storeModel = $this->resolveStore($store);
        $payload = $request->validated();

        $this->checkoutAbuseGuard->guard($request, $storeModel, $payload);

        $result = $this->checkoutIdempotency->handle(
            request: $request,
            store: $storeModel,
            payload: $payload,
            createOrder: fn (): Order => $createQuickOrder->handle($storeModel, QuickOrderData::fromArray($payload)),
        );

        return $this->checkoutResponse($result);
    }

    private function checkoutResponse(CheckoutOrderResult $result): JsonResponse
    {
        return OrderResource::make($result->order)
            ->response()
            ->setStatusCode($result->statusCode);
    }

    public function trackOrder(Request $request, string $store): OrderResource
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $storeModel = $this->resolveStore($store);
        $phone = preg_replace('/[\s.\-]/', '', $validated['phone']);

        $order = Order::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $storeModel->tenant_id)
            ->where('store_id', $storeModel->id)
            ->where('order_number', $validated['order_number'])
            ->whereHas('customer', fn (Builder $query): Builder => $query
                ->withoutGlobalScope('current_tenant')
                ->where('phone', $phone))
            ->with(['customer', 'items'])
            ->firstOrFail();

        return OrderResource::make($order);
    }

    private function resolveStore(string $identifier): Store
    {
        return Store::query()
            ->with(['tenant', 'storeSetting', 'themeSetting'])
            ->where(function (Builder $query) use ($identifier): void {
                $query->whereKey($identifier)
                    ->orWhere('subdomain', $identifier)
                    ->orWhere('domain', $identifier)
                    ->orWhere('slug', $identifier);
            })
            ->where('status', StoreStatus::Active->value)
            ->whereHas('tenant', fn (Builder $query): Builder => $query->whereIn('status', [
                TenantStatus::Active->value,
                TenantStatus::Trial->value,
            ]))
            ->firstOrFail();
    }

    private function abortIfStorefrontUnavailable(Store $store): void
    {
        $store->loadMissing(['tenant', 'storeSetting', 'themeSetting']);

        abort_unless($store->status === StoreStatus::Active, 404, 'Store not found.');
        abort_unless(in_array($store->tenant?->status, [
            TenantStatus::Active,
            TenantStatus::Trial,
        ], true), 404, 'Store not found.');
    }

    /**
     * @return Builder<Product>
     */
    private function productQuery(Store $store): Builder
    {
        return Product::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id);
    }

    /**
     * @return Builder<Category>
     */
    private function categoryQuery(Store $store): Builder
    {
        return Category::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id)
            ->where('status', CategoryStatus::Active);
    }
}
