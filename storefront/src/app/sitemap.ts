import type { MetadataRoute } from "next";
import { getAllProducts, getCategories, getHome } from "@/lib/api";
import { storefrontBaseUrl, storefrontUrl } from "@/lib/seo";
import { getActiveStoreContext } from "@/lib/store-context";
import { storeSetting } from "@/lib/theme";
import type { LegalPageKey } from "@/lib/types";

export const dynamic = "force-dynamic";

const legalPages: LegalPageKey[] = ["terms", "privacy", "returns", "shipping"];

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const context = await getActiveStoreContext();

  if (context === null) {
    return [];
  }

  const baseUrl = await storefrontBaseUrl();
  const [home, products, categories] = await Promise.all([
    getHome(context.identifier).catch(() => null),
    getAllProducts(context.identifier, { per_page: 48 }).catch(() => []),
    getCategories(context.identifier).catch(() => []),
  ]);
  const store = home?.store ?? context.store;
  const setting = storeSetting(store);
  const entries: MetadataRoute.Sitemap = [
    {
      url: storefrontUrl("/", baseUrl),
      changeFrequency: "daily",
      priority: 1,
    },
    {
      url: storefrontUrl("/products", baseUrl),
      changeFrequency: "daily",
      priority: 0.9,
    },
  ];

  for (const product of products) {
    entries.push({
      url: storefrontUrl(`/products/${product.slug}`, baseUrl),
      lastModified: product.published_at ? new Date(product.published_at) : undefined,
      changeFrequency: "daily",
      priority: product.is_featured ? 0.85 : 0.75,
    });
  }

  for (const category of categories) {
    entries.push({
      url: storefrontUrl(`/categories/${category.slug}`, baseUrl),
      changeFrequency: "weekly",
      priority: 0.7,
    });
  }

  for (const page of legalPages) {
    if (setting?.legal_pages?.[page] && setting.legal_content?.[page]) {
      entries.push({
        url: storefrontUrl(`/legal/${page}`, baseUrl),
        changeFrequency: "monthly",
        priority: 0.35,
      });
    }
  }

  return entries;
}
