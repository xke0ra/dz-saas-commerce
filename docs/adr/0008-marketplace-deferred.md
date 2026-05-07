# ADR 0008: Marketplace Is Deferred

Date: 2026-05-07

Status: Accepted

## Context

The product currently serves tenant-owned stores and storefronts. Marketplace mode would add cross-tenant catalog discovery, commission, settlement, moderation, disputes, search ranking, and stronger compliance needs.

## Decision

Do not implement marketplace mode now. Keep architecture compatible with a future marketplace, but optimize current work for merchant-owned storefront SaaS.

## Consequences

- Do not add cross-tenant public catalog features without a new ADR.
- Tenant isolation remains strict.
- Billing, shipping, checkout, and support workflows should target individual tenant stores first.
- Marketplace work is postponed until production readiness and merchant onboarding are stronger.
