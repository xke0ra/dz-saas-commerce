import type { Metadata } from "next";
import Link from "next/link";
import { ArrowLeft, Grid2X2, Sparkles } from "lucide-react";
import { JsonLd } from "@/components/storefront/json-ld";
import { ProductCard } from "@/components/storefront/product-card";
import { StoreContactStrip } from "@/components/storefront/store-contact-strip";
import { StoreShell } from "@/components/storefront/store-shell";
import { StoreTrustBadges } from "@/components/storefront/store-trust-badges";
import { StoreUnavailable } from "@/components/storefront/store-unavailable";
import { assetUrl, getHome } from "@/lib/api";
import { getStorefrontCopy, storeLocale } from "@/lib/i18n";
import { buildStorefrontMetadata, storefrontBaseUrl, storefrontUrl } from "@/lib/seo";
import { getActiveStoreContext } from "@/lib/store-context";
import { storeJsonLd } from "@/lib/structured-data";
import { themeSetting } from "@/lib/theme";

export const dynamic = "force-dynamic";

export async function generateMetadata(): Promise<Metadata> {
  const context = await getActiveStoreContext();

  if (context === null) {
    return {};
  }

  const home = await getHome(context.identifier).catch(() => null);

  if (home === null) {
    return {};
  }

  return buildStorefrontMetadata({
    store: home.store,
    path: "/",
  });
}

export default async function HomePage() {
  const context = await getActiveStoreContext();

  if (context === null) {
    return <StoreUnavailable />;
  }

  const home = await getHome(context.identifier).catch(() => null);

  if (home === null) {
    return <StoreUnavailable />;
  }

  const theme = themeSetting(home.store);
  const locale = storeLocale(home.store);
  const copy = getStorefrontCopy(locale);
  const heroImage = assetUrl(theme?.hero_image_path);
  const heroTitle = theme?.hero_title || home.store.name;
  const heroSubtitle = theme?.hero_subtitle || copy.home.fallbackSubtitle;
  const baseUrl = await storefrontBaseUrl();

  return (
    <StoreShell store={home.store}>
      <JsonLd data={storeJsonLd(home.store, storefrontUrl("/", baseUrl))} />

      <section className="grid gap-6 md:grid-cols-[1.2fr_.8fr] md:items-stretch">
        <div className="overflow-hidden rounded-md border border-border bg-white shadow-sm">
          {heroImage ? (
            <div className="h-56 bg-muted md:h-72">
              <img src={heroImage} alt="" className="h-full w-full object-cover" />
            </div>
          ) : null}
          <div className="p-6 md:p-8">
            <p className="inline-flex items-center gap-2 rounded-md bg-primary/10 px-3 py-1 text-sm font-bold text-primary">
              <Sparkles size={16} aria-hidden="true" />
              {copy.home.badge}
            </p>
            <h1 className="mt-4 text-3xl font-extrabold leading-tight md:text-5xl">{heroTitle}</h1>
            <p className="mt-4 max-w-2xl text-base leading-8 text-muted-foreground">{heroSubtitle}</p>
            <div className="mt-6 flex flex-wrap gap-3">
              <Link
                href="/products"
                className="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-primary px-5 text-base font-bold text-primary-foreground transition hover:bg-primary/90"
              >
                {copy.home.productsCta}
                <ArrowLeft size={18} aria-hidden="true" />
              </Link>
              <Link
                href="/track-order"
                className="inline-flex h-12 items-center justify-center rounded-md border border-border bg-white px-5 text-base font-bold transition hover:bg-muted/70"
              >
                {copy.home.trackOrderCta}
              </Link>
            </div>
          </div>
        </div>

        <div className="rounded-md border border-border bg-foreground p-5 text-white shadow-sm">
          <p className="text-sm text-white/70">{copy.home.categoriesKicker}</p>
          <div className="mt-4 grid gap-2">
            {home.categories.slice(0, 6).map((category) => (
              <Link
                key={category.id}
                href={`/categories/${category.slug}`}
                className="flex items-center justify-between rounded-md bg-white/10 px-3 py-3 transition hover:bg-white/16"
              >
                <span className="font-semibold">{category.name}</span>
                <Grid2X2 size={16} aria-hidden="true" />
              </Link>
            ))}
            {home.categories.length === 0 ? <p className="text-sm text-white/70">{copy.home.noCategories}</p> : null}
          </div>
        </div>
      </section>

      <StoreTrustBadges locale={locale} />

      <section className="mt-10">
        <div className="mb-5 flex items-center justify-between gap-4">
          <div>
            <p className="text-sm font-bold text-primary">{copy.home.featuredKicker}</p>
            <h2 className="text-2xl font-extrabold">{copy.home.featuredTitle}</h2>
          </div>
          <Link className="text-sm font-bold text-primary hover:underline" href="/products">
            {copy.common.allProducts}
          </Link>
        </div>

        {home.featured_products.length > 0 ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {home.featured_products.map((product) => (
              <ProductCard key={product.id} product={product} locale={locale} />
            ))}
          </div>
        ) : (
          <div className="rounded-md border border-border bg-white p-6 text-center text-muted-foreground">
            {copy.home.noFeatured}
          </div>
        )}
      </section>

      <StoreContactStrip store={home.store} />
    </StoreShell>
  );
}
