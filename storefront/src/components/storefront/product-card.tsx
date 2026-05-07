import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { AddToCartButton } from "@/components/storefront/add-to-cart-button";
import { assetUrl, productImages, unwrapResource } from "@/lib/api";
import { currencyLocale, formatMoney } from "@/lib/format";
import { getStorefrontCopy, type StoreLocale } from "@/lib/i18n";
import type { Product } from "@/lib/types";

export function ProductCard({ product, locale = "ar" }: { product: Product; locale?: StoreLocale | string }) {
  const image = productImages(product).find((item) => item.is_primary) ?? productImages(product)[0];
  const imageSrc = assetUrl(image?.path);
  const category = product.category ? unwrapResource(product.category) : null;
  const copy = getStorefrontCopy(locale);
  const cartProduct = {
    id: product.id,
    name: product.name,
    slug: product.slug,
    sku: product.sku,
    price_minor: product.price_minor,
    currency: product.currency,
    image_url: imageSrc,
  };

  return (
    <article className="group overflow-hidden rounded-md border border-border bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-soft">
      <Link href={`/products/${product.slug}`} className="block">
        <div className="relative aspect-square overflow-hidden bg-muted">
          {imageSrc ? (
            <img
              src={imageSrc}
              alt={image?.alt ?? product.name}
              className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
              loading="lazy"
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center bg-[linear-gradient(135deg,rgb(16_112_98/.16),rgb(181_72_54/.12))] px-6 text-center text-lg font-bold text-foreground">
              {product.name}
            </div>
          )}
        </div>
      </Link>

      <div className="space-y-3 p-4">
        {category ? <p className="text-xs font-medium text-muted-foreground">{category.name}</p> : null}
        <div>
          <Link href={`/products/${product.slug}`} className="line-clamp-2 min-h-12 font-bold hover:text-primary">
            {product.name}
          </Link>
          {product.short_description ? (
            <p className="mt-1 line-clamp-2 text-sm leading-6 text-muted-foreground">{product.short_description}</p>
          ) : null}
        </div>

        <div className="grid gap-3">
          <div>
            <p className="text-lg font-extrabold text-primary">
              {formatMoney(product.price_minor, product.currency, currencyLocale(locale))}
            </p>
            {product.compare_at_price_minor ? (
              <p className="text-xs text-muted-foreground line-through">
                {formatMoney(product.compare_at_price_minor, product.currency, currencyLocale(locale))}
              </p>
            ) : null}
          </div>
          <div className="grid grid-cols-[1fr_auto] gap-2">
            <AddToCartButton product={cartProduct} locale={locale} size="sm" className="w-full" />
            <Link
              href={`/products/${product.slug}`}
              aria-label={`${copy.product.openProductAria}: ${product.name}`}
              className="inline-flex h-9 w-10 items-center justify-center rounded-md bg-foreground text-white transition hover:bg-primary"
            >
              <ArrowLeft size={16} aria-hidden="true" />
            </Link>
          </div>
        </div>
      </div>
    </article>
  );
}
