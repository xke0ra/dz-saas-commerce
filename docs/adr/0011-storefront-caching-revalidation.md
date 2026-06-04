# ADR 0011: Storefront Caching And Revalidation

Date: 2026-05-07  
Last updated: 2026-05-31

Status: Proposed

## Context

The storefront uses Next.js 15 and currently prioritizes correctness and tenant/store resolution over performance. All API fetches use `cache: "no-store"`, meaning every page load hits the Laravel backend with no caching. This is correct for launch correctness but will not scale under real traffic.

Current confirmed behavior (as of 2026-05-31):

- All storefront API fetches: `cache: "no-store"` (in `storefront/src/lib/api.ts`)
- Geography endpoints (wilayas/communes): no cache headers, returned fresh on every checkout page load
- Product listing/detail: no cache
- Category listing: no cache

Known bottleneck: `GET /api/storefront/geography/communes` returns all 1,541 communes on every checkout page load with no caching.

## Decision

Define caching per route and data type before broad production traffic. Each route type requires an explicit caching rule:

| Route | Data Type | Proposed Cache Strategy |
|-------|-----------|------------------------|
| Store resolution | Tenant config | Short TTL (1-5 min), keyed by host |
| Geography (wilayas/communes) | Mostly static | Long TTL (24h+), HTTP Cache-Control |
| Product listing | Tenant catalog | Medium TTL (5-15 min), revalidate on publish |
| Product detail | Tenant catalog | Medium TTL (5-15 min), revalidate on publish |
| Category listing | Tenant catalog | Medium TTL (5-15 min) |
| SEO metadata (sitemap, robots) | Tenant config | Long TTL (1h+) |
| Checkout/cart mutations | Sensitive | Always `no-store` |
| Order tracking | Sensitive | Always `no-store` |

## Consequences

- Do not introduce broad caching that can leak tenant/store data across stores.
- Product and theme changes need a revalidation trigger (tag-based ISR or on-demand revalidation).
- Checkout and cart mutation flows must remain uncached permanently.
- Geography endpoints should be the first target — high frequency, fully static data.
- This ADR moves to `Accepted` when:
  1. At least geography and product routes have explicit cache/revalidation rules implemented.
  2. Tests or smoke proof confirm tenant data does not leak between stores.
  3. Cache behavior is documented per route in this ADR or a linked document.
