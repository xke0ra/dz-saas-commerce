# Storefront SEO And Crawl Contract

Last updated: 2026-05-08

This document defines the current public storefront SEO contract.

## Purpose

Each tenant storefront must expose crawlable, store-specific metadata without trusting frontend business values. SEO is a storefront rendering concern; product price, stock, discount, shipping, and checkout totals remain backend concerns.

## Current Files

- `storefront/src/lib/seo.ts`
- `storefront/src/app/sitemap.ts`
- `storefront/src/app/robots.ts`
- `storefront/src/app/page.tsx`
- `storefront/src/app/products/page.tsx`
- `storefront/src/app/products/[slug]/page.tsx`
- `storefront/src/app/categories/[slug]/page.tsx`
- `storefront/src/app/legal/[page]/page.tsx`
- `storefront/tests/e2e/storefront.spec.ts`

## Current Behavior

- `sitemap.xml` is dynamic and resolved per active storefront context.
- `robots.txt` is dynamic and points to the current host sitemap.
- Sitemap includes:
  - store home
  - product listing
  - product detail pages
  - category pages
  - enabled legal pages with content
- Current scale caveat: `storefront/src/app/sitemap.ts` requests products with `per_page=500`, but the backend products endpoint currently caps `per_page` at 48. Until sitemap pagination or a dedicated sitemap endpoint is implemented, the sitemap does not prove full product coverage for stores with more than 48 visible products.
- Robots allows public storefront pages and disallows:
  - `/cart`
  - `/search`
  - `/track-order`
- Public pages generate canonical links.
- Home, products, product details, categories, legal pages, cart, search, and track order generate store-aware metadata.
- Product detail pages generate OpenGraph article metadata.
- Home and product detail pages expose basic JSON-LD structured data.
- Search, cart, and track order pages are marked `noindex`.

## Base URL Rules

SEO URLs prefer explicit environment configuration:

```text
NEXT_PUBLIC_STOREFRONT_BASE_URL
STOREFRONT_BASE_URL
```

If no base URL is configured, the storefront derives the current protocol and host from request headers:

- `x-forwarded-proto`
- `x-forwarded-host`
- `host`

This keeps local development, subdomains, and future custom domains compatible.

## Test Coverage

Current Playwright coverage verifies:

- `sitemap.xml` contains home, product, category, and legal URLs.
- `robots.txt` points to the sitemap and disallows private/customer-action pages.
- Product detail pages expose title, canonical link, OpenGraph title, and OpenGraph type.
- Product detail pages expose Product and BreadcrumbList JSON-LD.

Verification commands:

```bash
cd storefront
pnpm typecheck
pnpm build
pnpm test:e2e
```

## Next SEO Work

- Add product-specific SEO fields in the backend when catalog maturity requires them.
- Add product image OpenGraph coverage when real product media is consistently seeded.
- Expand structured data JSON-LD for organization, legal pages, and richer product fields.
- Add dynamic sitemap pagination if stores can exceed the safe sitemap URL limit.
- Fix the current 48-product effective cap before relying on sitemap coverage for larger stores.
- Add SEO smoke checks for custom domains once domain routing is exercised end to end.
