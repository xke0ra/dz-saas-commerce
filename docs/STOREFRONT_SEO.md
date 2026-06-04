# Storefront SEO And Crawl Contract

Last updated: 2026-05-31

This document defines the current public storefront SEO contract.

## Purpose

Each tenant storefront must expose crawlable, store-specific metadata without trusting frontend business values. SEO is a storefront rendering concern; product price, stock, discount, shipping, and checkout totals remain backend concerns.

## Current Files

- `storefront/src/lib/seo.ts`
- `storefront/src/lib/structured-data.ts`
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
  - product detail pages (simple and variable — one URL per product, not per variant)
  - category pages
  - enabled legal pages with content
- Product URLs are collected through the paginated products API instead of assuming one oversized page. This removes the previous effective 48-product sitemap cap.
- Robots allows public storefront pages and disallows:
  - `/cart`
  - `/search`
  - `/track-order`
- Public pages generate canonical links.
- Home, products, product details, categories, legal pages, cart, search, and track order generate store-aware metadata.
- Product detail pages generate OpenGraph article metadata.
- Home and product detail pages expose basic JSON-LD structured data.
- Search, cart, and track order pages are marked `noindex`.

## Variable Product SEO Rules (ADR 0013)

Variable products share a single canonical URL at `/products/{slug}`. Variants are not separate SEO pages.

- The product detail page renders one canonical URL regardless of which variant is selected in the UI.
- Structured data (`Product` JSON-LD) uses the product base price or the first active variant price when the product is variable and has variants.
- `product.type` is exposed by the API and used by the storefront only for rendering; it has no effect on canonical URL structure.
- Variant option selections are client-side state only — they must not appear in canonical URLs or sitemap entries.
- `availability` in Product JSON-LD reflects `InStock` when any active variant has available inventory; `OutOfStock` otherwise.

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
- `sitemap.xml` follows product pagination and includes product URLs beyond the first backend page.
- `robots.txt` points to the sitemap and disallows private/customer-action pages.
- Product detail pages expose title, canonical link, OpenGraph title, and OpenGraph type.
- Product detail pages expose Product and BreadcrumbList JSON-LD.

Latest local verification: `pnpm audit --audit-level moderate`, `pnpm build`, sequential `pnpm typecheck`, and `pnpm test:e2e` passed on 2026-05-12, with Playwright reporting `6 passed` on Next.js `15.5.18`. The full Docker verification path also passed on 2026-05-12.

Verification commands:

```bash
cd storefront
pnpm audit --audit-level moderate
pnpm build
pnpm typecheck
pnpm test:e2e
```

## Next SEO Work

- Add product-specific SEO fields in the backend when catalog maturity requires them.
- Add product image OpenGraph coverage when real product media is consistently seeded.
- Expand structured data JSON-LD: organization, legal pages, richer product fields, variant availability per JSON-LD `hasVariant` if warranted.
- Add sitemap index support if stores can exceed the safe per-sitemap URL limit.
- Add SEO smoke checks for custom domains once domain routing is exercised end to end.
- Validate structured data output for variable products with Google Rich Results Test.
