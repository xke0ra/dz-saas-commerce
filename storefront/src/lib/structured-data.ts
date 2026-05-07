import "server-only";

import { unwrapResource } from "@/lib/api";
import { localeTag, storeLocale } from "@/lib/i18n";
import { storeSetting } from "@/lib/theme";
import type { Product, Store } from "@/lib/types";

type BreadcrumbItem = {
  name: string;
  url: string;
};

export function storeJsonLd(store: Store, url: string): Record<string, unknown> {
  const setting = storeSetting(store);

  return cleanJson({
    "@context": "https://schema.org",
    "@type": "Store",
    name: store.name,
    url,
    telephone: setting?.public_phone ?? setting?.support_phone ?? null,
    email: setting?.public_email ?? null,
    currenciesAccepted: store.currency,
    areaServed: "DZ",
    inLanguage: localeTag(storeLocale(store)),
    address: setting?.seller_address
      ? {
          "@type": "PostalAddress",
          streetAddress: setting.seller_address,
          addressCountry: "DZ",
        }
      : null,
  });
}

export function productJsonLd({
  product,
  store,
  url,
  image,
}: {
  product: Product;
  store: Store;
  url: string;
  image: string | null;
}): Record<string, unknown> {
  const category = product.category ? unwrapResource(product.category) : null;
  const availability =
    product.inventory?.track_quantity && product.inventory.available_quantity <= 0 && !product.inventory.allow_backorders
      ? "https://schema.org/OutOfStock"
      : "https://schema.org/InStock";

  return cleanJson({
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.name,
    description: product.short_description ?? product.description,
    sku: product.sku,
    image: image ? [image] : null,
    category: category?.name ?? null,
    url,
    brand: {
      "@type": "Brand",
      name: store.name,
    },
    offers: {
      "@type": "Offer",
      url,
      priceCurrency: product.currency,
      price: (product.price_minor / 100).toFixed(2),
      availability,
      seller: {
        "@type": "Store",
        name: store.name,
      },
    },
  });
}

export function breadcrumbJsonLd(items: BreadcrumbItem[]): Record<string, unknown> {
  return cleanJson({
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: item.url,
    })),
  });
}

function cleanJson(value: unknown): Record<string, unknown> {
  return removeEmpty(value) as Record<string, unknown>;
}

function removeEmpty(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map(removeEmpty).filter((item) => item !== null && item !== undefined);
  }

  if (typeof value === "object" && value !== null) {
    return Object.fromEntries(
      Object.entries(value)
        .map(([key, item]) => [key, removeEmpty(item)] as const)
        .filter(([, item]) => item !== null && item !== undefined && item !== ""),
    );
  }

  return value;
}
