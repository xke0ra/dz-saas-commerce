import { ProductCard } from "@/components/storefront/product-card";
import { StoreShell } from "@/components/storefront/store-shell";
import { StoreUnavailable } from "@/components/storefront/store-unavailable";
import { getProducts } from "@/lib/api";
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
    path: "/products",
    title: `${copy.products.metadataTitle} | ${context.store.name}`,
    description: copy.products.title,
  });
}

export default async function ProductsPage({
  searchParams,
}: {
  searchParams: Promise<{ category?: string }>;
}) {
  const context = await getActiveStoreContext();

  if (context === null) {
    return <StoreUnavailable />;
  }

  const params = await searchParams;
  const locale = storeLocale(context.store);
  const copy = getStorefrontCopy(locale);
  const products = await getProducts(context.identifier, { category: params.category, per_page: 48 }).catch(() => null);

  if (products === null) {
    return <StoreUnavailable locale={locale} title={copy.products.loadErrorTitle} detail={copy.common.loadError} />;
  }

  return (
    <StoreShell store={context.store}>
      <div className="mb-6">
        <p className="text-sm font-bold text-primary">{copy.products.kicker}</p>
        <h1 className="text-3xl font-extrabold">{copy.products.title}</h1>
      </div>

      {products.length > 0 ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} locale={locale} />
          ))}
        </div>
      ) : (
        <div className="rounded-md border border-border bg-white p-6 text-center text-muted-foreground">
          {copy.products.empty}
        </div>
      )}
    </StoreShell>
  );
}
