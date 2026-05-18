"use client";

import { useMemo, useState } from "react";
import { AddToCartButton } from "@/components/storefront/add-to-cart-button";
import type { CartProductInput } from "@/components/storefront/cart-provider";
import { QuickOrderForm } from "@/components/storefront/quick-order-form";
import { currencyLocale, formatMoney } from "@/lib/format";
import { getStorefrontCopy, type StoreLocale } from "@/lib/i18n";
import type { Product, ProductOptionValue, ProductVariant } from "@/lib/types";
import { cn } from "@/lib/utils";

export function ProductVariantPurchasePanel({
  product,
  cartProduct,
  storeIdentifier,
  locale = "ar",
}: {
  product: Product;
  cartProduct: CartProductInput;
  storeIdentifier: string;
  locale?: StoreLocale | string;
}) {
  const copy = getStorefrontCopy(locale);
  const variants = product.variants ?? [];
  const options = product.options ?? [];
  const hasVariants = variants.length > 0;
  const defaultVariant = useMemo(() => selectDefaultVariant(variants), [variants]);
  const [selectedOptions, setSelectedOptions] = useState<Record<string, string>>(
    () => defaultVariant?.selected_options ?? {},
  );
  const allOptionsSelected = options.every((option) => Boolean(selectedOptions[option.name]));
  const selectedVariant = hasVariants
    ? options.length === 0
      ? defaultVariant
      : allOptionsSelected
        ? findVariantForOptions(variants, selectedOptions, options.map((option) => option.name))
        : null
    : null;
  const priceMinor = selectedVariant?.effective_price_minor ?? product.price_minor;
  const compareAtPriceMinor = selectedVariant
    ? selectedVariant.compare_at_price_minor ?? product.compare_at_price_minor
    : product.compare_at_price_minor;
  const variantTitle = selectedVariant?.title ?? selectedVariant?.option_signature ?? null;
  const checkoutDisabledReason = hasVariants
    ? !allOptionsSelected
      ? copy.product.chooseVariant
      : selectedVariant === null
        ? copy.product.unavailableCombination
        : selectedVariant.is_available
          ? null
          : copy.product.outOfStock
    : null;
  const currentCartProduct = selectedVariant
    ? variantCartProduct(product, cartProduct, selectedVariant, variantTitle)
    : cartProduct;

  function selectOption(optionName: string, value: ProductOptionValue): void {
    setSelectedOptions((current) => ({
      ...current,
      [optionName]: value.value,
    }));
  }

  return (
    <>
      <section className="rounded-md border border-border bg-white p-5 shadow-sm">
        <div className="flex flex-wrap items-end gap-3">
          <p data-testid="product-detail-price" className="text-3xl font-extrabold text-primary">
            {formatMoney(priceMinor, product.currency, currencyLocale(locale))}
          </p>
          {compareAtPriceMinor ? (
            <p className="text-sm text-muted-foreground line-through">
              {formatMoney(compareAtPriceMinor, product.currency, currencyLocale(locale))}
            </p>
          ) : null}
        </div>

        {hasVariants ? (
          <div className="mt-5 grid gap-4 border-t border-border pt-5">
            <div>
              <h2 className="text-sm font-bold">{copy.product.optionsTitle}</h2>
              {selectedVariant ? (
                <p className="mt-1 text-sm text-muted-foreground">
                  {copy.product.selectedVariant}: {variantTitle}
                </p>
              ) : null}
            </div>

            {options.map((option) => (
              <div key={option.id} className="grid gap-2">
                <p className="text-sm font-semibold">{option.name}</p>
                <div className="flex flex-wrap gap-2">
                  {option.values.map((value) => {
                    const selected = selectedOptions[option.name] === value.value;

                    return (
                      <button
                        key={value.id}
                        type="button"
                        aria-pressed={selected}
                        className={cn(
                          "min-h-10 rounded-md border px-3 py-2 text-sm font-semibold transition",
                          selected
                            ? "border-primary bg-primary text-primary-foreground"
                            : "border-border bg-white hover:border-primary hover:bg-primary/5",
                        )}
                        onClick={() => selectOption(option.name, value)}
                      >
                        {value.value}
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}

            <div className="grid gap-1 rounded-md bg-background px-3 py-2 text-sm">
              {selectedVariant?.sku ? (
                <p data-testid="variant-sku" className="font-semibold">
                  {copy.product.skuLabel}: {selectedVariant.sku}
                </p>
              ) : null}
              <p
                data-testid="variant-availability"
                className={cn(
                  "font-semibold",
                  selectedVariant?.is_available ? "text-primary" : "text-danger",
                )}
              >
                {variantAvailabilityLabel(selectedVariant, copy)}
              </p>
            </div>
          </div>
        ) : null}

        <AddToCartButton
          product={currentCartProduct}
          locale={locale}
          size="lg"
          disabled={Boolean(checkoutDisabledReason)}
          disabledLabel={checkoutDisabledReason ?? undefined}
          className="mt-5 w-full sm:w-auto"
        />
      </section>

      <QuickOrderForm
        storeIdentifier={storeIdentifier}
        productId={product.id}
        productVariantId={selectedVariant?.id ?? null}
        productName={product.name}
        locale={locale}
        checkoutDisabledReason={checkoutDisabledReason}
      />
    </>
  );
}

function selectDefaultVariant(variants: ProductVariant[]): ProductVariant | null {
  return variants.find((variant) => variant.is_available) ?? variants[0] ?? null;
}

function findVariantForOptions(
  variants: ProductVariant[],
  selectedOptions: Record<string, string>,
  optionNames: string[],
): ProductVariant | null {
  return variants.find((variant) =>
    optionNames.every((optionName) => variant.selected_options[optionName] === selectedOptions[optionName]),
  ) ?? null;
}

function variantCartProduct(
  product: Product,
  cartProduct: CartProductInput,
  variant: ProductVariant,
  variantTitle: string | null,
): CartProductInput {
  return {
    ...cartProduct,
    id: `${product.id}:${variant.id}`,
    product_id: product.id,
    product_variant_id: variant.id,
    sku: variant.sku ?? product.sku,
    variant_title: variantTitle,
    selected_options: variant.selected_options,
    price_minor: variant.effective_price_minor,
  };
}

function variantAvailabilityLabel(
  variant: ProductVariant | null,
  copy: ReturnType<typeof getStorefrontCopy>,
): string {
  if (variant === null) {
    return copy.product.unavailableCombination;
  }

  if (!variant.is_available) {
    return copy.product.outOfStock;
  }

  if (typeof variant.available_quantity === "number") {
    return copy.product.availableQuantity(variant.available_quantity);
  }

  return copy.product.available;
}
