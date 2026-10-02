# Stage 8: Storefront Functional Audit

**Audit Date:** 2026-10-01  
**Audit Branch:** `main`  
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`  

---

## 1. Storefront Architecture & Routing

The customer-facing application in `storefront/` is built on Next.js 15 (`15.5.18`), React 19 (`19.0.0`), TypeScript (`5.7.0`), and Tailwind CSS (`3.4.17`):

### Store Resolution Pipeline
- Handled server-side by `getActiveStoreContext()` (`storefront/src/lib/store-context.ts`, protected with `"server-only"`).
- Extracts incoming `x-forwarded-host` or `host` headers.
- Queries `/api/storefront/resolve?host={host}`.
- If unresolvable, falls back to `NEXT_PUBLIC_DEFAULT_STORE` or `DEFAULT_STORE_IDENTIFIER` (development/testing mode).
- If resolution fails completely, renders a clean fallback `<StoreUnavailable />` component rather than unhandled 500 errors.

### Server vs. Client Component Boundaries
- **Server Components:**
  - `app/page.tsx` (Storefront home)
  - `app/products/page.tsx` & `app/products/[slug]/page.tsx`
  - `app/categories/page.tsx` & `app/categories/[slug]/page.tsx`
  - `app/search/page.tsx`
  - `app/cart/page.tsx`
  - `app/track-order/page.tsx`
  - `app/legal/[page]/page.tsx`
  - `app/sitemap.ts` & `app/robots.ts`
- **Client Interactive Primitives ("use client"):**
  - `ProductVariantPurchasePanel`: Interactive variant picker, dynamic price preview, option combination availability checking.
  - `QuickOrderForm`: Direct single-product checkout with wilaya/commune cascade and Zod validation.
  - `CartProvider`: Store-scoped React Context storing line items in `localStorage` key `dz_storefront_cart_${storeIdentifier}`.
  - `CartCheckout`: Multi-item checkout form submitting items payload.
  - `TrackOrderForm`: Order status lookup.

---

## 2. SEO, Structured Data & Metadata

1. **Metadata Generation:**
   - Every page exports dynamic `generateMetadata()` leveraging `buildStorefrontMetadata()` (`storefront/src/lib/seo.ts`).
   - Supports OpenGraph tags, dynamic store canonical URLs, and localized titles/descriptions.
2. **Structured Data (JSON-LD):**
   - Injected via `<JsonLd />` component (`storefront/src/components/storefront/json-ld.tsx`).
   - Schemas: `Schema.org/Store`, `Schema.org/Product`, and `Schema.org/BreadcrumbList`.
3. **Robots Policy (`app/robots.ts`):**
   - Disallows crawling of sensitive/transactional routes: `/cart`, `/search`, `/track-order`.
   - Declares canonical sitemap location.

---

## 3. Storefront Findings

### Finding `WEB-001`: Inefficient Sitemap Product Traversal (N+1 HTTP Calls)
- **Severity:** MEDIUM
- **Status:** CONFIRMED IMPLEMENTED
- **File:** `storefront/src/app/sitemap.ts` (line 22), `storefront/src/lib/api.ts` (lines 133–149)
- **Description:** `sitemap.ts` calls `getAllProducts()`, which sequentially fetches all product pages in chunks of 48:
  ```ts
  for (let page = currentPage + 1; page <= lastPage; page += 1) {
    const nextPage = await getProductsPage(store, { ...query, page, per_page: perPage });
    products.push(...unwrapCollection(nextPage));
  }
  ```
- **Impact:** For a store with 5,000 products, generating `sitemap.xml` triggers 105 sequential HTTP requests to the backend. This causes slow sitemap responses, excessive backend load, and risks hitting the 120 req/min API rate limit.
- **Recommended Fix:** Implement a dedicated backend sitemap endpoint (`/api/storefront/{store}/sitemap-urls`) that streams product slugs and timestamps in a single optimized SQL query, or introduce Next.js sitemap caching/revalidation.

### Finding `WEB-002`: Uncached Storefront Rendering Under High Concurrency
- **Severity:** MEDIUM
- **Status:** PARTIAL
- **Reference:** Proposed ADR 0011
- **Files:** `storefront/src/app/page.tsx`, `storefront/src/lib/api.ts`
- **Description:** All pages specify `export const dynamic = "force-dynamic"`, and `storefrontFetch` forces `cache: "no-store"`.
- **Impact:** Every single customer visit to the home page or catalog executes fresh HTTP round-trips to Laravel and fresh PostgreSQL queries.
- **Recommended Fix:** Adopt incremental static regeneration (ISR) or time-based revalidation (e.g. `revalidate = 60`) for public catalog pages as proposed in ADR 0011.
