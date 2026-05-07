import "server-only";

import type { Metadata } from "next";
import { headers } from "next/headers";
import { assetUrl, productImages } from "@/lib/api";
import { localeTag, storeLocale } from "@/lib/i18n";
import { storeSeo, themeSetting } from "@/lib/theme";
import type { Product, Store } from "@/lib/types";

type StorefrontMetadataInput = {
  store: Store;
  path?: string;
  title?: string;
  description?: string | null;
  image?: string | null;
  type?: "website" | "article";
  index?: boolean;
};

export async function buildStorefrontMetadata({
  store,
  path = "/",
  title,
  description,
  image,
  type = "website",
  index = true,
}: StorefrontMetadataInput): Promise<Metadata> {
  const baseUrl = await storefrontBaseUrl();
  const seo = storeSeo(store);
  const metadataTitle = title || seo.title;
  const metadataDescription = description || seo.description;
  const canonicalUrl = storefrontUrl(path, baseUrl);
  const imageUrl = image || storeSeoImage(store);

  return {
    metadataBase: baseUrl,
    title: metadataTitle,
    description: metadataDescription,
    alternates: {
      canonical: canonicalUrl,
    },
    openGraph: {
      title: metadataTitle,
      description: metadataDescription,
      url: canonicalUrl,
      siteName: store.name,
      locale: localeTag(storeLocale(store)).replace("-", "_"),
      type,
      images: imageUrl ? [{ url: imageUrl, alt: metadataTitle }] : undefined,
    },
    twitter: {
      card: imageUrl ? "summary_large_image" : "summary",
      title: metadataTitle,
      description: metadataDescription,
      images: imageUrl ? [imageUrl] : undefined,
    },
    robots: index
      ? {
          index: true,
          follow: true,
        }
      : {
          index: false,
          follow: false,
        },
  };
}

export async function storefrontBaseUrl(): Promise<URL> {
  const configuredUrl = process.env.NEXT_PUBLIC_STOREFRONT_BASE_URL ?? process.env.STOREFRONT_BASE_URL;

  if (configuredUrl) {
    return new URL(withTrailingSlash(configuredUrl));
  }

  const requestHeaders = await headers();
  const host = requestHeaders.get("x-forwarded-host") ?? requestHeaders.get("host") ?? "localhost:3000";
  const forwardedProto = requestHeaders.get("x-forwarded-proto")?.split(",")[0]?.trim();
  const protocol = forwardedProto || (host.startsWith("localhost") || host.startsWith("127.0.0.1") ? "http" : "https");

  return new URL(`${protocol}://${host}`);
}

export function storefrontUrl(path: string, baseUrl: URL): string {
  return new URL(normalizePath(path), baseUrl).toString();
}

export function productSeoImage(product: Product): string | null {
  const primaryImage = productImages(product).find((image) => image.is_primary) ?? productImages(product)[0];

  return assetUrl(primaryImage?.path);
}

export function storeSeoImage(store: Store): string | null {
  const theme = themeSetting(store);

  return assetUrl(theme?.hero_image_path) ?? assetUrl(theme?.logo_path);
}

function normalizePath(path: string): string {
  return path.startsWith("/") ? path : `/${path}`;
}

function withTrailingSlash(url: string): string {
  return url.endsWith("/") ? url : `${url}/`;
}
