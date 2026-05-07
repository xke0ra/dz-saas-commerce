"use client";

import Link from "next/link";
import { ShoppingCart } from "lucide-react";
import { useCart } from "@/components/storefront/cart-provider";
import { getStorefrontCopy, type StoreLocale } from "@/lib/i18n";

export function CartNavLink({ locale = "ar" }: { locale?: StoreLocale | string }) {
  const copy = getStorefrontCopy(locale);
  const { totalQuantity } = useCart();

  return (
    <Link
      href="/cart"
      aria-label={copy.common.cartAria}
      className="inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-3 py-2 hover:bg-muted/70"
    >
      <span className="relative inline-flex">
        <ShoppingCart size={17} aria-hidden="true" />
        {totalQuantity > 0 ? (
          <span className="absolute -end-2 -top-2 min-w-4 rounded-full bg-primary px-1 text-center text-[10px] font-bold leading-4 text-primary-foreground">
            {totalQuantity}
          </span>
        ) : null}
      </span>
      {copy.common.cart}
    </Link>
  );
}
