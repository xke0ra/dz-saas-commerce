<?php

namespace App\Actions\Checkout;

use App\Actions\Coupons\CalculateCouponDiscount;
use App\Data\Checkout\QuickOrderData;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\PlanFeatureKey;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
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
            $products = $this->loadProducts($tenantId, array_keys($items));

            if ($products->count() !== count($items)) {
                throw ValidationException::withMessages([
                    'items' => 'One or more products are unavailable.',
                ]);
            }

            $shippingRate = $this->findShippingRate($tenantId, $data);
            $paymentMethod = $this->findPaymentMethod($tenantId);
            $subtotal = 0;
            $reservedInventoryItems = [];

            foreach ($products as $product) {
                $quantity = $items[$product->id];
                $subtotal += $product->price_minor * $quantity;

                $inventoryItem = $this->reserveInventory($tenantId, $product, $quantity);

                if ($inventoryItem !== null) {
                    $reservedInventoryItems[$product->id] = $inventoryItem;
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

            foreach ($products as $product) {
                $quantity = $items[$product->id];

                $orderItem = $order->items()->create([
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $quantity,
                    'unit_price_minor' => $product->price_minor,
                    'total_minor' => $product->price_minor * $quantity,
                    'metadata' => [],
                ]);

                if (isset($reservedInventoryItems[$product->id])) {
                    $this->recordStockReservation(
                        inventoryItem: $reservedInventoryItems[$product->id],
                        product: $product,
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
     * @param  array<int, array{product_id: string, quantity: int}>  $items
     * @return array<string, int>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $productId = $item['product_id'];

            if (array_key_exists($productId, $normalized)) {
                throw ValidationException::withMessages([
                    'items' => 'Duplicate products are not allowed in the same checkout.',
                ]);
            }

            $normalized[$productId] = $item['quantity'];
        }

        return $normalized;
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

    private function reserveInventory(string $tenantId, Product $product, int $quantity): ?InventoryItem
    {
        $inventoryItem = InventoryItem::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
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
        Order $order,
        OrderItem $orderItem,
        int $quantity,
    ): void {
        StockMovement::query()
            ->withoutGlobalScope('current_tenant')
            ->create([
                'tenant_id' => $order->tenant_id,
                'product_id' => $product->id,
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
