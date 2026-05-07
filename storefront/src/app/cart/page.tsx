import { CartCheckout } from "@/components/storefront/cart-checkout";
import { StoreShell } from "@/components/storefront/store-shell";
import { StoreUnavailable } from "@/components/storefront/store-unavailable";
import { getStorefrontCopy, storeLocale } from "@/lib/i18n";
import { buildStorefrontMetadata } from "@/lib/seo";
import { getActiveStoreContext } from "@/lib/store-context";

export const dynamic = "force-dynamic";

export async function generateMetadata() {
  const context = await getActiveStoreContext();

  if (context === null) {
    return {};
  }

  const locale = storeLocale(context.store);
  const copy = getStorefrontCopy(locale);

  return buildStorefrontMetadata({
    store: context.store,
    path: "/cart",
    title: `${copy.common.cart} | ${context.store.name}`,
    description: copy.cart.emptyDetail,
    index: false,
  });
}

export default async function CartPage() {
  const context = await getActiveStoreContext();

  if (context === null) {
    return <StoreUnavailable />;
  }

  const locale = storeLocale(context.store);
  const copy = getStorefrontCopy(locale);

  return (
    <StoreShell store={context.store}>
      <CartCheckout storeIdentifier={context.identifier} locale={locale} />
      <span className="sr-only">{copy.common.cart}</span>
    </StoreShell>
  );
}
