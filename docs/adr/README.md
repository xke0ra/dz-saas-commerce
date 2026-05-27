# Architecture Decision Records

Last updated: 2026-05-27

This directory records architecture decisions that should not be changed casually by humans or AI agents.

Format:

- Status: `Accepted`, `Proposed`, `Superseded`, or `Deprecated`.
- Context: what the repository currently proves.
- Decision: the chosen direction.
- Consequences: what future work must respect.

Current ADRs:

1. `0001-modular-monolith.md`
2. `0002-shared-database-tenancy.md`
3. `0003-laravel-filament-backend.md`
4. `0004-nextjs-storefront.md`
5. `0005-backend-source-of-truth-for-commerce-money.md`
6. `0006-do-not-trust-client-totals.md`
7. `0007-69-wilayas-not-enabled-now.md`
8. `0008-marketplace-deferred.md`
9. `0009-manual-payments-first.md`
10. `0010-algerian-shipping-strategy.md`
11. `0011-storefront-caching-revalidation.md`
12. `0012-production-deployment-topology.md` - proposed topology; mayfairs staging partially proves Caddy/nginx/2FA/demo, while backup/monitoring/rollback remain pending.
13. `0013-product-variants-inventory-design.md` - accepted product variants/options and sellable-unit inventory design; implementation chain through product type enforcement is complete.
