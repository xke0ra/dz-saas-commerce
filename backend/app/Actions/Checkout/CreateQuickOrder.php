<?php

namespace App\Actions\Checkout;

use App\Actions\Coupons\CalculateCouponDiscount;
use App\Data\Checkout\QuickOrderData;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\PlanFeatureKey;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Models\StockMovement;
use App\Models\Store;
use App\Support\Billing\SubscriptionFeatureGate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateQuickOrder
{
    public function __construct(
        private readonly SubscriptionFeatureGate $featureGate,
        private readonly CalculateCouponDiscount $calculateCouponDiscount,
    ) {}

    public function handle(Store $store, QuickOrderData $data): Order
    {
        return DB::transaction(function () use ($store, $data): Order {
            $tenantId = $store->tenant_id;
            $this->featureGate->ensureWithinLimit($tenantId, PlanFeatureKey::MaxOrdersPerMonth);

            $items = $this->normalizeItems($data->items);
            $productIds = $this->productIds($items);
            $products = $this->loadProducts($tenantId, $productIds);

            if ($products->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'items' => 'One or more products are unavailable.',
                ]);
            }

            $variants = $this->loadVariants($tenantId, $this->variantIds($items));

            $this->validateVariants($items, $products, $variants);

            $shippingRate = $this->findShippingRate($tenantId, $data);
            $paymentMethod = $this->findPaymentMethod($tenantId);
            $subtotal = 0;
            $reservedInventoryItems = [];

            foreach ($items as $itemKey => $item) {
                $product = $products->get($item['product_id']);
                $variant = $this->variantForItem($item, $variants);
                $quantity = $item['quantity'];
                $unitPriceMinor = $this->unitPriceMinor($product, $variant);

                $subtotal += $unitPriceMinor * $quantity;

                $inventoryItem = $this->reserveInventory($tenantId, $product, $variant, $quantity);

                if ($inventoryItem !== null) {
                    $reservedInventoryItems[$itemKey] = $inventoryItem;
                }
            }

            $customer = Customer::query()
                ->withoutGlobalScope('current_tenant')
                ->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'phone' => $data->phone,
                    ],
                    [
                        'full_name' => $data->fullName,
                        'wilaya_id' => $data->wilayaId,
                        'commune_id' => $data->communeId,
                        'address' => $data->address,
                    ],
                );

            $couponDiscount = $this->calculateCouponDiscount->handle(
                tenantId: $tenantId,
                couponCode: $data->couponCode,
                subtotalMinor: $subtotal,
            );
            $discount = $couponDiscount?->discountMinor ?? 0;

            $order = Order::query()
                ->withoutGlobalScope('current_tenant')
                ->create([
                    'tenant_id' => $tenantId,
                    'store_id' => $store->id,
                    'customer_id' => $customer->id,
                    'coupon_id' => $couponDiscount?->coupon->id,
                    'order_number' => $this->generateOrderNumber($tenantId),
                    'status' => OrderStatus::Pending,
                    'payment_status' => PaymentStatus::Unpaid,
                    'delivery_type' => $data->deliveryType,
                    'wilaya_id' => $data->wilayaId,
                    'commune_id' => $data->communeId,
                    'shipping_address' => $data->address,
                    'customer_note' => $data->note,
                    'subtotal_minor' => $subtotal,
                    'shipping_fee_minor' => $shippingRate->price_minor,
                    'discount_minor' => $discount,
                    'total_minor' => $subtotal + $shippingRate->price_minor - $discount,
                    'currency' => 'DZD',
                    'metadata' => [],
                ]);

            foreach ($items as $itemKey => $item) {
                $product = $products->get($item['product_id']);
                $variant = $this->variantForItem($item, $variants);
                $quantity = $item['quantity'];
                $unitPriceMinor = $this->unitPriceMinor($product, $variant);

                $orderItem = $order->items()->create([
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'variant_title' => $variant === null ? null : ($variant->title ?? $variant->option_signature),
                    'variant_sku' => $variant?->sku,
                    'selected_options' => $this->selectedOptions($variant),
                    'quantity' => $quantity,
                    'unit_price_minor' => $unitPriceMinor,
                    'total_minor' => $unitPriceMinor * $quantity,
                    'metadata' => [],
                ]);

                if (isset($reservedInventoryItems[$itemKey])) {
                    $this->recordStockReservation(
                        inventoryItem: $reservedInventoryItems[$itemKey],
                        product: $product,
                        variant: $variant,
                        order: $order,
                        orderItem: $orderItem,
                        quantity: $quantity,
                    );
                }
            }

            $order->statusHistories()->create([
                'tenant_id' => $tenantId,
                'from_status' => null,
                'to_status' => OrderStatus::Pending,
                'comment' => 'Quick order submitted from storefront.',
            ]);

            $order->payments()->create([
                'tenant_id' => $tenantId,
                'payment_method_id' => $paymentMethod->id,
                'status' => PaymentStatus::Pending,
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
                'metadata' => [],
            ]);

            if ($couponDiscount !== null) {
                $order->couponRedemptions()->create([
                    'tenant_id' => $tenantId,
                    'coupon_id' => $couponDiscount->coupon->id,
                    'customer_id' => $customer->id,
                    'code' => $couponDiscount->coupon->code,
                    'discount_minor' => $couponDiscount->discountMinor,
                    'currency' => $order->currency,
                    'metadata' => [],
                ]);

                $couponDiscount->coupon->increment('used_count');
            }

            return $order->load(['customer', 'items', 'payments.paymentMethod', 'coupon', 'couponRedemptions', 'wilaya', 'commune']);
        });
    }

    /**
     * @param  array<int, array{product_id: string, product_variant_id?: ?string, quantity: int}>  $items
     * @return array<string, array{product_id: string, product_variant_id: ?string, quantity: int}>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];
        $parentProducts = [];
        $variantProducts = [];
        $variants = [];

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $variantId = $item['product_variant_id'] ?? null;
            $variantId = is_string($variantId) && $variantId !== '' ? $variantId : null;

            if ($variantId === null) {
                if (isset($variantProducts[$productId])) {
                    throw ValidationException::withMessages([
                        'items' => 'Cart cannot mix parent product and variants for the same product.',
                    ]);
                }

                if (isset($parentProducts[$productId])) {
                    throw ValidationException::withMessages([
                        'items' => 'Duplicate products are not allowed in the same checkout.',
                    ]);
                }

                $parentProducts[$productId] = true;
                $normalized["product:{$productId}"] = [
                    'product_id' => $productId,
                    'product_variant_id' => null,
                    'quantity' => $item['quantity'],
                ];

                continue;
            }

            if (isset($parentProducts[$productId])) {
                throw ValidationException::withMessages([
                    'items' => 'Cart cannot mix parent product and variants for the same product.',
                ]);
            }

            if (isset($variants[$variantId])) {
                throw ValidationException::withMessages([
                    'items' => 'Duplicate product variants are not allowed in the same checkout.',
                ]);
            }

            $variantProducts[$productId] = true;
            $variants[$variantId] = true;
            $normalized["variant:{$variantId}"] = [
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $item['quantity'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, array{product_id: string, product_variant_id: ?string, quantity: int}>  $items
     * @return array<int, string>
     */
    private function productIds(array $items): array
    {
        return array_values(array_unique(array_map(
            fn (array $item): string => $item['product_id'],
            $items,
        )));
    }

    /**
     * @param  array<string, array{product_id: string, product_variant_id: ?string, quantity: int}>  $items
     * @return array<int, string>
     */
    private function variantIds(array $items): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (array $item): ?string => $item['product_variant_id'],
            $items,
        ))));
    }

    /**
     * @param  array<int, string>  $productIds
     * @return Collection<int, Product>
     */
    private function loadProducts(string $tenantId, array $productIds): Collection
    {
        return Product::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->whereKey($productIds)
            ->visibleOnStorefront()
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<int, string>  $variantIds
     * @return Collection<int, ProductVariant>
     */
    private function loadVariants(string $tenantId, array $variantIds): Collection
    {
        if ($variantIds === []) {
            return new Collection;
        }

        return ProductVariant::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->whereKey($variantIds)
            ->with(['optionValues.option'])
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  array<string, array{product_id: string, product_variant_id: ?string, quantity: int}>  $items
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function validateVariants(array $items, Collection $products, Collection $variants): void
    {
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $variantId = $item['product_variant_id'];

            if ($product === null) {
                throw ValidationException::withMessages([
                    'items' => 'One or more products are unavailable.',
                ]);
            }

            if ($product->type === ProductType::Variable && $variantId === null) {
                throw ValidationException::withMessages([
                    'items' => 'A product variant is required for this product.',
                ]);
            }

            if ($product->type === ProductType::Simple && $variantId !== null) {
                throw ValidationException::withMessages([
                    'items' => 'This product does not accept variants.',
                ]);
            }

            if ($variantId === null) {
                continue;
            }

            $variant = $variants->get($variantId);

            if ($variant === null) {
                throw ValidationException::withMessages([
                    'items' => 'One or more product variants are unavailable.',
                ]);
            }

            if ($product === null || $variant->product_id !== $item['product_id']) {
                throw ValidationException::withMessages([
                    'items' => 'The selected product variant does not belong to the selected product.',
                ]);
            }

            if ($variant->status !== ProductStatus::Active) {
                throw ValidationException::withMessages([
                    'items' => 'The selected product variant is unavailable.',
                ]);
            }

            $variant->setRelation('product', $product);
        }
    }

    /**
     * @param  array{product_id: string, product_variant_id: ?string, quantity: int}  $item
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function variantForItem(array $item, Collection $variants): ?ProductVariant
    {
        if ($item['product_variant_id'] === null) {
            return null;
        }

        return $variants->get($item['product_variant_id']);
    }

    private function unitPriceMinor(Product $product, ?ProductVariant $variant): int
    {
        if ($variant !== null) {
            return $variant->effectivePriceMinor();
        }

        return (int) $product->price_minor;
    }

    /**
     * @return array<string, string>|null
     */
    private function selectedOptions(?ProductVariant $variant): ?array
    {
        if ($variant === null) {
            return null;
        }

        $selectedOptions = [];

        foreach ($variant->optionValues as $optionValue) {
            $option = $optionValue->option;

            if ($option === null || $option->product_id !== $variant->product_id) {
                continue;
            }

            $selectedOptions[$option->name] = $optionValue->value;
        }

        return $selectedOptions === [] ? null : $selectedOptions;
    }

    private function findShippingRate(string $tenantId, QuickOrderData $data): ShippingRate
    {
        $shippingRate = ShippingRate::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->where('wilaya_id', $data->wilayaId)
            ->where('delivery_type', $data->deliveryType->value)
            ->where('is_active', true)
            ->where(function ($query) use ($data): void {
                $query->where('commune_id', $data->communeId)
                    ->orWhereNull('commune_id');
            })
            ->orderByRaw('commune_id is null')
            ->first();

        if ($shippingRate === null) {
            throw ValidationException::withMessages([
                'delivery_type' => 'Shipping is not available for the selected commune and delivery type.',
            ]);
        }

        return $shippingRate;
    }

    private function findPaymentMethod(string $tenantId): PaymentMethod
    {
        $paymentMethod = PaymentMethod::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->where('type', PaymentMethodType::CashOnDelivery->value)
            ->where('is_active', true)
            ->first();

        if ($paymentMethod === null) {
            throw ValidationException::withMessages([
                'payment_method' => 'Cash on delivery is not enabled for this store.',
            ]);
        }

        return $paymentMethod;
    }

    private function reserveInventory(string $tenantId, Product $product, ?ProductVariant $variant, int $quantity): ?InventoryItem
    {
        $inventoryItem = InventoryItem::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->when(
                $variant === null,
                fn ($query) => $query->whereNull('product_variant_id'),
                fn ($query) => $query->where('product_variant_id', $variant->id),
            )
            ->lockForUpdate()
            ->first();

        if ($inventoryItem === null || ! $inventoryItem->track_quantity) {
            return null;
        }

        if (! $inventoryItem->allow_backorders && $inventoryItem->availableQuantity() < $quantity) {
            throw ValidationException::withMessages([
                'items' => "Insufficient inventory for {$product->name}.",
            ]);
        }

        $inventoryItem->increment('reserved_quantity', $quantity);

        return $inventoryItem->refresh();
    }

    private function recordStockReservation(
        InventoryItem $inventoryItem,
        Product $product,
        ?ProductVariant $variant,
        Order $order,
        OrderItem $orderItem,
        int $quantity,
    ): void {
        StockMovement::query()
            ->withoutGlobalScope('current_tenant')
            ->create([
                'tenant_id' => $order->tenant_id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'inventory_item_id' => $inventoryItem->id,
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'order_return_id' => null,
                'actor_id' => null,
                'type' => StockMovementType::Reserved,
                'quantity_delta' => 0,
                'reserved_delta' => $quantity,
                'balance_quantity_after' => $inventoryItem->quantity,
                'balance_reserved_after' => $inventoryItem->reserved_quantity,
                'reason' => 'quick_checkout_reservation',
                'metadata' => [
                    'source' => 'quick_checkout',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'product_id' => $product->id,
                    'order_item_id' => $orderItem->id,
                ],
            ]);
    }

    private function generateOrderNumber(string $tenantId): string
    {
        do {
            $orderNumber = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Order::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->where('order_number', $orderNumber)
            ->exists());

        return $orderNumber;
    }
}
