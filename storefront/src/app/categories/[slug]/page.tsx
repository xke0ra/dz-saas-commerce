import type { Metadata } from "next";
import { ProductCard } from "@/components/storefront/product-card";
import { StoreShell } from "@/components/storefront/store-shell";
import { StoreUnavailable } from "@/components/storefront/store-unavailable";
import { getCategory, getProducts } from "@/lib/api";
import { getStorefrontCopy, storeLocale } from "@/lib/i18n";
import { buildStorefrontMetadata } from "@/lib/seo";
import { getActiveStoreContext } from "@/lib/store-context";

export const dynamic = "force-dynamic";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const context = await getActiveStoreContext();

  if (context === null) {
    return {};
  }

  const { slug } = await params;
  const category = await getCategory(context.identifier, slug).catch(() => null);

  if (category === null) {
    return {};
  }

  return buildStorefrontMetadata({
    store: context.store,
    path: `/categories/${category.slug}`,
    title: `${category.name} | ${context.store.name}`,
    description: category.description,
  });
}

export default async function CategoryPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const context = await getActiveStoreContext();

  if (context === null) {
    return <StoreUnavailable />;
  }

  const { slug } = await params;
  const locale = storeLocale(context.store);
  const copy = getStorefrontCopy(locale);
  const [category, products] = await Promise.all([
    getCategory(context.identifier, slug).catch(() => null),
    getProducts(context.identifier, { category: slug, per_page: 48 }).catch(() => null),
  ]);

  if (category === null || products === null) {
    return <StoreUnavailable locale={locale} title={copy.category.unavailableTitle} detail={copy.category.unavailableDetail} />;
  }

  return (
    <StoreShell store={context.store}>
      <div className="mb-6 rounded-md border border-border bg-white p-5 shadow-sm">
        <p className="text-sm font-bold text-primary">{copy.category.kicker}</p>
        <h1 className="text-3xl font-extrabold">{category.name}</h1>
        {category.description ? <p className="mt-2 leading-8 text-muted-foreground">{category.description}</p> : null}
      </div>

      {products.length > 0 ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} locale={locale} />
          ))}
        </div>
      ) : (
        <div className="rounded-md border border-border bg-white p-6 text-center text-muted-foreground">
          {copy.category.empty}
        </div>
      )}
    </StoreShell>
  );
}
