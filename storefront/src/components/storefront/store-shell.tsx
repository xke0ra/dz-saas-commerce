import { CartProvider } from "@/components/storefront/cart-provider";
import { StoreFooter } from "@/components/storefront/store-footer";
import { StoreHeader } from "@/components/storefront/store-header";
import { localeDirection, localeTag, storeLocale } from "@/lib/i18n";
import { storeThemeStyle } from "@/lib/theme";
import type { Store } from "@/lib/types";

export function StoreShell({
  store,
  children,
}: {
  store: Store;
  children: React.ReactNode;
}) {
  const locale = storeLocale(store);
  const storeCartId = store.id || store.slug || store.subdomain || store.domain || "default";

  return (
    <div lang={localeTag(locale)} dir={localeDirection(locale)} style={storeThemeStyle(store)} className="min-h-screen">
      <CartProvider storeId={storeCartId}>
        <StoreHeader store={store} />
        <main className="mx-auto w-full max-w-6xl px-4 py-8 md:py-10">{children}</main>
        <StoreFooter store={store} />
      </CartProvider>
    </div>
  );
}
