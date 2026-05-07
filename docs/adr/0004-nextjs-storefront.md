# ADR 0004: Separate Next.js Storefront

Date: 2026-05-07

Status: Accepted

## Context

The customer storefront is implemented in `storefront/` with Next.js 15, React 19, TypeScript, Tailwind CSS, store resolution, product listing/details, cart, checkout proxying, SEO metadata, sitemap, robots, and JSON-LD helpers.

## Decision

Keep the public storefront as a separate Next.js application that talks to the Laravel backend API.

## Consequences

- Laravel remains the source of truth for data and commerce calculations.
- Next.js owns rendering, public UX, SEO, and storefront routing.
- Storefront API contracts must be tested when checkout, product, SEO, or tenant resolution changes.
- Caching/revalidation decisions require explicit documentation and tests.
