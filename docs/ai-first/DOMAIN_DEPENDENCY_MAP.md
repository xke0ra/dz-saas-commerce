# Domain Dependency Map

Last updated: 2026-06-02

## Dependency Matrix

| Domain | Depends On | Used By |
|---|---|---|
| Tenancy | Users, tenant roles, current tenant service | Almost every backend domain |
| Stores | Tenancy, settings, themes | Storefront, readiness, domains |
| Catalog | Tenancy, stores, categories | Storefront, checkout, inventory |
| Variants | Catalog, inventory | Storefront product detail, checkout |
| Inventory | Catalog, variants, orders | Checkout, fulfillment, returns |
| Checkout | Stores, catalog, variants, inventory, shipping, payments, coupons, billing | Orders, payments, storefront confirmation |
| Orders | Checkout, customers, inventory | Payments, shipping, returns, analytics |
| Payments | Orders, payment methods | Billing, order operations |
| Shipping | Orders, shipping rates, companies | Fulfillment, returns |
| Returns | Orders, inventory, payments | Stock movement, refunds |
| Billing | Tenancy, plans, feature flags | Feature gates, subscriptions |
| Domains | Stores, tenancy, DNS lookup | Storefront host resolution |
| Security/audit | Users, policies, actions | All sensitive domains |
| Operations | Health checker, deploy scripts, env contracts | Release, staging, production |
| Storefront | Backend API, store context, theme/settings | Public customer experience |

## High-Coupling Areas

- Checkout is the highest-coupling domain. Changes there must inspect both
  backend and storefront contracts.
- Tenancy is a system-wide dependency. Any loosened tenant boundary has
  platform-wide impact.
- Inventory and variants are tightly coupled through `product_variant_id`.
- Operations documentation depends on actual deploy scripts and evidence files.

## Low-Coupling Areas

- Static documentation indexes.
- ADR summaries, when not changing decisions.
- Template files, when not changing operational semantics.
- Presentation-only storefront styling, when not changing API payloads,
  checkout, SEO, or behavior.
