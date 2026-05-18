"use client";

import { useState } from "react";
import Link from "next/link";
import { Minus, Plus, Trash2 } from "lucide-react";
import { QuickOrderForm } from "@/components/storefront/quick-order-form";
import { type CartItem, useCart } from "@/components/storefront/cart-provider";
import { Button } from "@/components/ui/button";
import { currencyLocale, formatMoney } from "@/lib/format";
import { getStorefrontCopy, type StoreLocale } from "@/lib/i18n";

export function CartCheckout({
  storeIdentifier,
  locale = "ar",
}: {
  storeIdentifier: string;
  locale?: StoreLocale | string;
}) {
  const copy = getStorefrontCopy(locale);
  const { items, updateQuantity, removeItem, clearCart } = useCart();
  const [confirmedItems, setConfirmedItems] = useState<CartItem[] | null>(null);
  const displayedItems = confirmedItems ?? items;
  const isCheckoutComplete = confirmedItems !== null;
  const totalQuantity = displayedItems.reduce((total, item) => total + item.quantity, 0);
  const checkoutItems = displayedItems.map((item) => ({
    product_id: item.product_id ?? item.id,
    ...(item.product_variant_id ? { product_variant_id: item.product_variant_id } : {}),
    quantity: item.quantity,
  }));

  if (displayedItems.length === 0) {
    return (
      <section className="rounded-md border border-border bg-white p-8 text-center shadow-sm">
        <h1 className="text-2xl font-extrabold">{copy.cart.emptyTitle}</h1>
        <p className="mt-2 text-muted-foreground">{copy.cart.emptyDetail}</p>
        <Link
          href="/products"
          className="mt-5 inline-flex h-11 items-center justify-center rounded-md bg-primary px-5 text-sm font-bold text-primary-foreground hover:bg-primary/90"
        >
          {copy.cart.continueShopping}
        </Link>
      </section>
    );
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_.9fr]">
      <section className="rounded-md border border-border bg-white p-4 shadow-sm md:p-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-bold text-primary">{copy.cart.kicker}</p>
            <h1 className="text-2xl font-extrabold">{copy.cart.title}</h1>
            <p className="mt-1 text-sm text-muted-foreground">{copy.cart.itemsSummary(totalQuantity)}</p>
          </div>
          {!isCheckoutComplete ? (
            <Button type="button" variant="secondary" size="sm" onClick={clearCart}>
              {copy.cart.clear}
            </Button>
          ) : null}
        </div>

        {!isCheckoutComplete ? (
          <a
            href="#cart-checkout"
            className="mt-4 inline-flex h-11 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-bold text-primary-foreground hover:bg-primary/90 lg:hidden"
          >
            {copy.cart.goToCheckout}
          </a>
        ) : null}

        <div className="mt-5 divide-y divide-border overflow-hidden rounded-md border border-border">
          {displayedItems.map((item) => (
            <article key={item.id} className="grid gap-4 p-4 sm:grid-cols-[72px_1fr_auto] sm:items-center">
              <Link href={`/products/${item.slug}`} className="block h-20 w-20 overflow-hidden rounded-md bg-muted">
                {item.image_url ? (
                  <img src={item.image_url} alt="" className="h-full w-full object-cover" />
                ) : (
                  <span className="flex h-full w-full items-center justify-center px-2 text-center text-xs font-bold">
                    {item.name}
                  </span>
                )}
              </Link>

              <div className="min-w-0">
                <Link href={`/products/${item.slug}`} className="font-bold hover:text-primary">
                  {item.name}
                </Link>
                {item.sku ? <p className="mt-1 text-xs text-muted-foreground">{item.sku}</p> : null}
                {item.variant_title ? <p className="mt-1 text-xs font-semibold text-muted-foreground">{item.variant_title}</p> : null}
                {Object.keys(item.selected_options ?? {}).length > 0 ? (
                  <p className="mt-1 text-xs text-muted-foreground">
                    {Object.entries(item.selected_options ?? {})
                      .map(([name, value]) => `${name}: ${value}`)
                      .join(" · ")}
                  </p>
                ) : null}
                <p className="mt-2 text-sm font-bold text-primary">
                  {formatMoney(item.price_minor, item.currency, currencyLocale(locale))}
                </p>
              </div>

              {isCheckoutComplete ? (
                <p className="text-sm font-bold text-muted-foreground">
                  {copy.cart.quantitySummary(item.quantity)}
                </p>
              ) : (
                <div className="flex items-center justify-between gap-3 sm:justify-end">
                  <div className="flex h-10 items-center overflow-hidden rounded-md border border-border">
                    <button
                      type="button"
                      className="grid h-10 w-10 place-items-center hover:bg-muted"
                      aria-label={copy.cart.decrease}
                      onClick={() => updateQuantity(item.id, item.quantity - 1)}
                    >
                      <Minus size={15} aria-hidden="true" />
                    </button>
                    <span className="w-10 text-center text-sm font-bold">{item.quantity}</span>
                    <button
                      type="button"
                      className="grid h-10 w-10 place-items-center hover:bg-muted"
                      aria-label={copy.cart.increase}
                      onClick={() => updateQuantity(item.id, item.quantity + 1)}
                    >
                      <Plus size={15} aria-hidden="true" />
                    </button>
                  </div>
                  <button
                    type="button"
                    className="grid h-10 w-10 place-items-center rounded-md text-danger hover:bg-danger/10"
                    aria-label={copy.cart.remove}
                    onClick={() => removeItem(item.id)}
                  >
                    <Trash2 size={17} aria-hidden="true" />
                  </button>
                </div>
              )}
            </article>
          ))}
        </div>

        <p className="mt-4 rounded-md bg-background px-3 py-2 text-sm text-muted-foreground">
          {copy.cart.finalTotalNotice}
        </p>
      </section>

      <div id="cart-checkout" className="scroll-mt-28">
        <QuickOrderForm
          storeIdentifier={storeIdentifier}
          productName={copy.cart.checkoutTitle}
          locale={locale}
          checkoutItems={checkoutItems}
          onOrderCreated={() => {
            setConfirmedItems(items);
            clearCart();
          }}
          submitLabel={copy.cart.submit}
        />
      </div>
    </div>
  );
}
