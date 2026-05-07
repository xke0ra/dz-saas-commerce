import type { MetadataRoute } from "next";
import { storefrontBaseUrl, storefrontUrl } from "@/lib/seo";

export const dynamic = "force-dynamic";

export default async function robots(): Promise<MetadataRoute.Robots> {
  const baseUrl = await storefrontBaseUrl();

  return {
    rules: {
      userAgent: "*",
      allow: "/",
      disallow: ["/cart", "/search", "/track-order"],
    },
    sitemap: storefrontUrl("/sitemap.xml", baseUrl),
    host: baseUrl.origin,
  };
}
