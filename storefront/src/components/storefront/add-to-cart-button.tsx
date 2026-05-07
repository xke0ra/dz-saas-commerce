"use client";

import { Check, ShoppingCart } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { useCart, type CartProductInput } from "@/components/storefront/cart-provider";
import { getStorefrontCopy, type StoreLocale } from "@/lib/i18n";

export function AddToCartButton({
  product,
  locale = "ar",
  size = "md",
  className,
}: {
  product: CartProductInput;
  locale?: StoreLocale | string;
  size?: "sm" | "md" | "lg";
  className?: string;
}) {
  const copy = getStorefrontCopy(locale);
  const { addItem } = useCart();
  const [added, setAdded] = useState(false);

  function handleAdd() {
    addItem(product, 1);
    setAdded(true);
    window.setTimeout(() => setAdded(false), 1400);
  }

  return (
    <Button type="button" size={size} variant={added ? "accent" : "primary"} className={className} onClick={handleAdd}>
      {added ? <Check size={17} aria-hidden="true" /> : <ShoppingCart size={17} aria-hidden="true" />}
      {added ? copy.product.addedToCart : copy.product.addToCart}
    </Button>
  );
}
