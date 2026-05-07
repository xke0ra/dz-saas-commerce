import type { Metadata } from "next";
import Link from "next/link";
import { ArrowRight, CheckCircle2, ShieldCheck, Truck } from "lucide-react";
import { AddToCartButton } from "@/components/storefront/add-to-cart-button";
import { JsonLd } from "@/components/storefront/json-ld";
import { QuickOrderForm } from "@/components/storefront/quick-order-form";
import { StoreShell } from "@/components/storefront/store-shell";
import { StoreUnavailable } from "@/components/storefront/store-unavailable";
import { assetUrl, getProduct, productImages, unwrapResource } from "@/lib/api";
import { currencyLocale, formatMoney } from "@/lib/format";
import { getStorefrontCopy, storeLocale } from "@/lib/i18n";
import { buildStorefrontMetadata, productSeoImage, storefrontBaseUrl, storefrontUrl } from "@/lib/seo";
import { getActiveStoreContext } from "@/lib/store-context";
import { breadcrumbJsonLd, productJsonLd } from "@/lib/structured-data";

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
  const product = await getProduct(context.identifier, slug).catch(() => null);

  if (product === null) {
    return {};
  }

  return buildStorefrontMetadata({
    store: context.store,
    path: `/products/${product.slug}`,
    title: `${product.name} | ${context.store.name}`,
    description: product.short_description,
    image: productSeoImage(product),
    type: "article",
  });
}

export default async function ProductDetailsPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const context = await getActiveStoreContext();

  if (context === null) {
    return <StoreUnavailable />;
  }

  const { slug } = await params;
  const product = await getProduct(context.identifier, slug).catch(() => null);
  const locale = storeLocale(context.store);
  const copy = getStorefrontCopy(locale);

  if (product === null) {
    return <StoreUnavailable locale={locale} title={copy.product.unavailableTitle} detail={copy.product.unavailableDetail} />;
  }

  const images = productImages(product);
  const primaryImage = images.find((image) => image.is_primary) ?? images[0];
  const category = product.category ? unwrapResource(product.category) : null;
  const primaryImageSrc = assetUrl(primaryImage?.path);
  const baseUrl = await storefrontBaseUrl();
  const productUrl = storefrontUrl(`/products/${product.slug}`, baseUrl);
  const cartProduct = {
    id: product.id,
    name: product.name,
    slug: product.slug,
    sku: product.sku,
    price_minor: product.price_minor,
    currency: product.currency,
    image_url: primaryImageSrc,
  };

  return (
    <StoreShell store={context.store}>
      <JsonLd
        data={[
          productJsonLd({
            product,
            store: context.store,
            url: productUrl,
            image: primaryImageSrc,
          }),
          breadcrumbJsonLd([
            { name: context.store.name, url: storefrontUrl("/", baseUrl) },
            ...(category ? [{ name: category.name, url: storefrontUrl(`/categories/${category.slug}`, baseUrl) }] : []),
            { name: product.name, url: productUrl },
          ]),
        ]}
      />

      <Link href="/products" className="mb-5 inline-flex items-center gap-2 text-sm font-bold text-primary hover:underline">
        <ArrowRight size={16} aria-hidden="true" />
        {copy.product.backToProducts}
      </Link>

      <section className="grid gap-6 lg:grid-cols-[1fr_.85fr]">
        <div className="space-y-4">
          <div className="overflow-hidden rounded-md border border-border bg-white shadow-sm">
            <div className="aspect-square bg-muted">
              {primaryImageSrc ? (
                <img src={primaryImageSrc} alt={primaryImage?.alt ?? product.name} className="h-full w-full object-cover" />
              ) : (
                <div className="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,rgb(16_112_98/.16),rgb(181_72_54/.12))] px-8 text-center text-3xl font-extrabold">
                  {product.name}
                </div>
              )}
            </div>
          </div>

          {images.length > 1 ? (
            <div className="grid grid-cols-4 gap-3">
              {images.slice(0, 8).map((image) => {
                const src = assetUrl(image.path);

                return src ? (
                  <div key={image.id} className="aspect-square overflow-hidden rounded-md border border-border bg-white">
                    <img src={src} alt={image.alt ?? product.name} className="h-full w-full object-cover" />
                  </div>
                ) : null;
              })}
            </div>
          ) : null}
        </div>

        <div className="space-y-5">
          <section className="rounded-md border border-border bg-white p-5 shadow-sm">
            {category ? <p className="text-sm font-bold text-primary">{category.name}</p> : null}
            <h1 className="mt-2 text-3xl font-extrabold leading-tight">{product.name}</h1>
            {product.short_description ? (
              <p className="mt-3 text-base leading-8 text-muted-foreground">{product.short_description}</p>
            ) : null}

            <div className="mt-5 flex flex-wrap items-end gap-3">
              <p className="text-3xl font-extrabold text-primary">
                {formatMoney(product.price_minor, product.currency, currencyLocale(locale))}
              </p>
              {product.compare_at_price_minor ? (
                <p className="text-sm text-muted-foreground line-through">
                  {formatMoney(product.compare_at_price_minor, product.currency, currencyLocale(locale))}
                </p>
              ) : null}
            </div>

            <AddToCartButton product={cartProduct} locale={locale} size="lg" className="mt-5 w-full sm:w-auto" />

            <div className="mt-5 grid gap-2 text-sm text-muted-foreground sm:grid-cols-3">
              <p className="flex items-center gap-2 rounded-md bg-background px-3 py-2">
                <Truck size={16} aria-hidden="true" />
                {copy.product.delivery}
              </p>
              <p className="flex items-center gap-2 rounded-md bg-background px-3 py-2">
                <ShieldCheck size={16} aria-hidden="true" />
                {copy.product.cod}
              </p>
              <p className="flex items-center gap-2 rounded-md bg-background px-3 py-2">
                <CheckCircle2 size={16} aria-hidden="true" />
                {copy.product.phoneConfirmation}
              </p>
            </div>

            {product.description ? (
              <div className="mt-6 border-t border-border pt-5">
                <h2 className="font-bold">{copy.product.descriptionTitle}</h2>
                <p className="mt-2 whitespace-pre-line leading-8 text-muted-foreground">{product.description}</p>
              </div>
            ) : null}
          </section>

          <QuickOrderForm
            storeIdentifier={context.identifier}
            productId={product.id}
            productName={product.name}
            locale={locale}
          />
        </div>
      </section>
    </StoreShell>
  );
}
