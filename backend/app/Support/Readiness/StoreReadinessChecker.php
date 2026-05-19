<?php

namespace App\Support\Readiness;

use App\Enums\PaymentMethodType;
use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\TenantStatus;
use App\Models\InventoryItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class StoreReadinessChecker
{
    public const MISSING_PAYMENT_METHOD = 'missing_payment_method';

    public const MISSING_SHIPPING_RATE = 'missing_shipping_rate';

    public const NO_SELLABLE_PRODUCTS = 'no_sellable_products';

    public const PRODUCT_MISSING_INVENTORY = 'product_missing_inventory';

    public const VARIABLE_PRODUCT_MISSING_VARIANTS = 'variable_product_missing_variants';

    public const VARIABLE_PRODUCT_MISSING_OPTIONS = 'variable_product_missing_options';

    public const VARIABLE_PRODUCT_NO_SELLABLE_VARIANTS = 'variable_product_no_sellable_variants';

    public const INVALID_PRODUCT_PRICE = 'invalid_product_price';

    public function check(Store $store, ?StoreStatus $targetStatus = null): ReadinessResult
    {
        $store->loadMissing(['tenant', 'storeSetting', 'themeSetting']);

        $errors = [];
        $warnings = [];

        if (blank($store->tenant_id) || $store->tenant === null) {
            $errors[] = $this->issue('missing_store_tenant', 'The store must belong to a tenant before it can be published.');
        }

        if (blank($store->subdomain)) {
            $errors[] = $this->issue('missing_store_subdomain', 'Set a store subdomain before publishing the storefront.');
        }

        $effectiveStatus = $targetStatus ?? $store->status;

        if ($effectiveStatus !== StoreStatus::Active) {
            $errors[] = $this->issue('store_not_active', 'The store must target the active status before it can be exposed on the storefront.');
        }

        if ($store->tenant !== null && ! in_array($store->tenant->status, [TenantStatus::Active, TenantStatus::Trial], true)) {
            $errors[] = $this->issue('tenant_not_operational', 'The tenant must be active or trialing before the storefront can be published.');
        }

        if ($store->storeSetting === null) {
            $errors[] = $this->issue('missing_store_settings', 'Create store settings before publishing the storefront.');
        } elseif (! $this->hasLegalContent($store)) {
            $warnings[] = $this->issue('missing_legal_content', 'Add legal and policy content before production launch.');
        }

        if ($store->themeSetting === null) {
            $errors[] = $this->issue('missing_theme_settings', 'Create active theme settings before publishing the storefront.');
        }

        if (! $this->hasActivePaymentMethod($store)) {
            $errors[] = $this->issue(self::MISSING_PAYMENT_METHOD, 'Enable at least one active storefront payment method for this tenant.');
        }

        if (! $this->hasActiveShippingRate($store)) {
            $errors[] = $this->issue(self::MISSING_SHIPPING_RATE, 'Create at least one active shipping rate for this tenant.');
        }

        if (! $this->hasSellableProduct($store)) {
            $errors[] = $this->issue(self::NO_SELLABLE_PRODUCTS, 'Publish at least one visible product with sellable inventory.');
        }

        return new ReadinessResult($errors, $warnings);
    }

    public function assertReady(Store $store, ?StoreStatus $targetStatus = null): void
    {
        $result = $this->check($store, $targetStatus);

        if ($result->ready()) {
            return;
        }

        throw ValidationException::withMessages([
            'readiness' => ['Store is not ready for publishing.'],
            'readiness_codes' => $result->errorCodes(),
        ]);
    }

    public function checkProduct(Product $product, ?ProductStatus $targetStatus = null): ReadinessResult
    {
        $errors = [];
        $warnings = [];

        if (! $this->productIsVisible($product, $targetStatus)) {
            $errors[] = $this->issue('product_not_published', 'The product must be active and not scheduled for a future publish date.');
        }

        if (! $this->hasValidPrice($product)) {
            $errors[] = $this->issue(self::INVALID_PRODUCT_PRICE, 'Set a non-negative product price before publishing.');
        }

        if ($product->isVariable()) {
            $errors = [
                ...$errors,
                ...$this->variableProductErrors($product),
            ];
        } else {
            $inventoryItem = $this->simpleInventoryItem($product);

            if (! $this->inventoryIsSellable($inventoryItem)) {
                $errors[] = $this->issue(self::PRODUCT_MISSING_INVENTORY, 'Create sellable product-level inventory before publishing this simple product.');
            }
        }

        if ($product->exists && ! $this->productHasImage($product)) {
            $warnings[] = $this->issue('product_missing_images', 'Add product images before production launch.');
        }

        return new ReadinessResult($errors, $warnings);
    }

    public function assertProductReady(Product $product, ?ProductStatus $targetStatus = null): void
    {
        $result = $this->checkProduct($product, $targetStatus);

        if ($result->ready()) {
            return;
        }

        throw ValidationException::withMessages([
            'readiness' => ['Product is not ready for publishing.'],
            'readiness_codes' => $result->errorCodes(),
        ]);
    }

    /**
     * @return array{code: string, message: string}
     */
    private function issue(string $code, string $message): array
    {
        return [
            'code' => $code,
            'message' => $message,
        ];
    }

    private function hasActivePaymentMethod(Store $store): bool
    {
        if (blank($store->tenant_id)) {
            return false;
        }

        return PaymentMethod::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id)
            ->where('type', PaymentMethodType::CashOnDelivery->value)
            ->where('is_active', true)
            ->exists();
    }

    private function hasActiveShippingRate(Store $store): bool
    {
        if (blank($store->tenant_id)) {
            return false;
        }

        return ShippingRate::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id)
            ->where('is_active', true)
            ->exists();
    }

    private function hasSellableProduct(Store $store): bool
    {
        if (blank($store->tenant_id)) {
            return false;
        }

        return Product::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id)
            ->visibleOnStorefront()
            ->get()
            ->contains(fn (Product $product): bool => $this->checkProduct($product)->ready());
    }

    private function hasLegalContent(Store $store): bool
    {
        $settings = $store->storeSetting;

        return $settings !== null
            && filled($settings->terms_content)
            && filled($settings->privacy_content)
            && filled($settings->return_policy_content)
            && filled($settings->shipping_policy_content);
    }

    private function productIsVisible(Product $product, ?ProductStatus $targetStatus = null): bool
    {
        $effectiveStatus = $targetStatus ?? $product->status;

        return $effectiveStatus === ProductStatus::Active
            && ($product->published_at === null || $product->published_at->lessThanOrEqualTo(now()));
    }

    private function hasValidPrice(Product $product): bool
    {
        return is_numeric($product->price_minor) && (int) $product->price_minor >= 0;
    }

    private function simpleInventoryItem(Product $product): ?InventoryItem
    {
        if (! $product->exists) {
            return null;
        }

        return InventoryItem::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->first();
    }

    private function inventoryIsSellable(?InventoryItem $inventoryItem): bool
    {
        if ($inventoryItem === null) {
            return false;
        }

        if (! $inventoryItem->track_quantity) {
            return true;
        }

        return $inventoryItem->allow_backorders || $inventoryItem->availableQuantity() > 0;
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    private function variableProductErrors(Product $product): array
    {
        if (! $product->exists) {
            return [
                $this->issue(self::VARIABLE_PRODUCT_MISSING_VARIANTS, 'Create at least one active variant before publishing this variable product.'),
            ];
        }

        $activeVariants = ProductVariant::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->where('status', ProductStatus::Active->value)
            ->with(['optionValues.option'])
            ->get();

        if ($activeVariants->isEmpty()) {
            return [
                $this->issue(self::VARIABLE_PRODUCT_MISSING_VARIANTS, 'Create at least one active variant before publishing this variable product.'),
            ];
        }

        $errors = [];

        if (! $this->allActiveVariantsHaveOptions($product, $activeVariants)) {
            $errors[] = $this->issue(self::VARIABLE_PRODUCT_MISSING_OPTIONS, 'Attach product option values to every active variant before publishing.');
        }

        if (! $this->hasSellableVariantInventory($product, $activeVariants)) {
            $errors[] = $this->issue(self::VARIABLE_PRODUCT_NO_SELLABLE_VARIANTS, 'Create sellable variant-level inventory before publishing this variable product.');
        }

        return $errors;
    }

    /**
     * @param  Collection<int, ProductVariant>  $activeVariants
     */
    private function allActiveVariantsHaveOptions(Product $product, Collection $activeVariants): bool
    {
        return $activeVariants->every(
            fn (ProductVariant $variant): bool => $variant->optionValues
                ->contains(fn (ProductOptionValue $value): bool => $value->option?->product_id === $product->id),
        );
    }

    /**
     * @param  Collection<int, ProductVariant>  $activeVariants
     */
    private function hasSellableVariantInventory(Product $product, Collection $activeVariants): bool
    {
        $inventoryItems = InventoryItem::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->whereIn('product_variant_id', $activeVariants->pluck('id')->all())
            ->get()
            ->keyBy('product_variant_id');

        return $activeVariants->contains(
            fn (ProductVariant $variant): bool => $this->inventoryIsSellable($inventoryItems->get($variant->id)),
        );
    }

    private function productHasImage(Product $product): bool
    {
        return $product->images()
            ->withoutGlobalScope('current_tenant')
            ->exists();
    }
}
