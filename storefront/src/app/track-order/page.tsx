import { StoreShell } from "@/components/storefront/store-shell";
import { StoreUnavailable } from "@/components/storefront/store-unavailable";
import { TrackOrderForm } from "@/components/storefront/track-order-form";
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
    path: "/track-order",
    title: `${copy.common.trackOrder} | ${context.store.name}`,
    description: copy.trackOrder.detail,
    index: false,
  });
}

export default async function TrackOrderPage() {
  const context = await getActiveStoreContext();

  if (context === null) {
    return <StoreUnavailable />;
  }

  return (
    <StoreShell store={context.store}>
      <TrackOrderForm storeIdentifier={context.identifier} locale={storeLocale(context.store)} />
    </StoreShell>
  );
}
