# ADR 0011: Storefront Caching And Revalidation

Date: 2026-05-07

Status: Proposed

## Context

The storefront uses Next.js and currently prioritizes correctness and tenant/store resolution. The roadmap notes heavy or broad `no-store` usage as an area requiring caching/revalidation work.

## Decision

Define caching per route and data type before broad production traffic. Store resolution, product listing, product details, SEO metadata, sitemap, and robots behavior should each have explicit caching rules.

## Consequences

- Do not introduce broad caching that can leak tenant/store data.
- Product and theme changes need revalidation strategy.
- Checkout and cart mutation flows must remain uncached.
- This ADR stays `Proposed` until implementation and tests prove the selected strategy.
