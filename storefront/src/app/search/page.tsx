import { ProductCard } from "@/components/storefront/product-card";
import { StoreShell } from "@/components/storefront/store-shell";
import { StoreUnavailable } from "@/components/storefront/store-unavailable";
import { searchProducts } from "@/lib/api";
import { getStorefrontCopy, storeLocale } from "@/lib/i18n";
import { buildStorefrontMetadata } from "@/lib/seo";
import { getActiveStoreContext } from "@/lib/store-context";

export const dynamic = "force-dynamic";

export async function generateMetadata({
  searchParams,
}: {
  searchParams: Promise<{ q?: string }>;
}) {
  const context = await getActiveStoreContext();

  if (context === null) {
    return {};
  }

  const params = await searchParams;
  const q = typeof params.q === "string" ? params.q.trim() : "";
  const locale = storeLocale(context.store);
  const copy = getStorefrontCopy(locale);

  return buildStorefrontMetadata({
    store: context.store,
    path: q ? `/search?q=${encodeURIComponent(q)}` : "/search",
    title: `${q ? copy.search.resultTitle(q) : copy.search.metadataTitle} | ${context.store.name}`,
    description: copy.search.title,
    index: false,
  });
}

export default async function SearchPage({
  searchParams,
}: {
  searchParams: Promise<{ q?: string }>;
}) {
  const context = await getActiveStoreContext();

  if (context === null) {
    return <StoreUnavailable />;
  }

  const params = await searchParams;
  const q = typeof params.q === "string" ? params.q.trim() : "";
  const locale = storeLocale(context.store);
  const copy = getStorefrontCopy(locale);
  const products = q.length >= 2 ? await searchProducts(context.identifier, q).catch(() => null) : [];

  if (products === null) {
    return <StoreUnavailable locale={locale} title={copy.search.loadErrorTitle} detail={copy.common.loadError} />;
  }

  return (
    <StoreShell store={context.store}>
      <div className="mb-6">
        <p className="text-sm font-bold text-primary">{copy.search.kicker}</p>
        <h1 className="text-3xl font-extrabold">{q ? copy.search.resultTitle(q) : copy.search.title}</h1>
      </div>

      {q.length < 2 ? (
        <div className="rounded-md border border-border bg-white p-6 text-center text-muted-foreground">
          {copy.search.minQuery}
        </div>
      ) : products.length > 0 ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} locale={locale} />
          ))}
        </div>
      ) : (
        <div className="rounded-md border border-border bg-white p-6 text-center text-muted-foreground">
          {copy.search.empty}
        </div>
      )}
    </StoreShell>
  );
}
