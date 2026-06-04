# Architecture Decision Records

Last updated: 2026-05-31

This directory records architecture decisions that should not be changed casually by humans or AI agents.

Format:

- Status: `Accepted`, `Proposed`, `Superseded`, or `Deprecated`.
- Context: what the repository currently proves.
- Decision: the chosen direction.
- Consequences: what future work must respect.

Read the relevant ADR before making any change that touches architecture, tenancy, money, storefront caching, deployment, marketplace, or shipping.

## Current ADRs

1. `0001-modular-monolith.md` — Keep monorepo modular monolith; no microservices without a new ADR.
2. `0002-shared-database-tenancy.md` — Shared PostgreSQL database with row-level tenant isolation.
3. `0003-laravel-filament-backend.md` — Laravel 13 + Filament 5.6 for backend and admin panels.
4. `0004-nextjs-storefront.md` — Separate Next.js 15 storefront talking to the Laravel API.
5. `0005-backend-source-of-truth-for-commerce-money.md` — All prices, discounts, totals calculated server-side.
6. `0006-do-not-trust-client-totals.md` — Backend never trusts amounts sent from the storefront.
7. `0007-69-wilayas-not-enabled-now.md` — Keep 58-wilaya dataset until authoritative 69-wilaya commune data exists.
8. `0008-marketplace-deferred.md` — No marketplace/multi-vendor mode in this phase.
9. `0009-manual-payments-first.md` — Manual payment confirmation before any payment gateway integration.
10. `0010-algerian-shipping-strategy.md` — Internal shipping configuration and manual workflows first.
11. `0011-storefront-caching-revalidation.md` — **Proposed.** Define caching per route before broad production traffic. Becomes Accepted when caching strategy is implemented and tested per acceptance criteria in the ADR.
12. `0012-production-deployment-topology.md` — **Proposed.** Reverse proxy + separate process supervision + managed services. Becomes Accepted when backup/monitoring/rollback are proven in production.
13. `0013-product-variants-inventory-design.md` — Product variants with sellable-unit inventory uniqueness. Implementation complete.
14. `0014-error-tracking-provider.md` — **Proposed.** Select and integrate error tracking before production. Becomes Accepted when a provider is selected, integrated, PII scrubber configured, and alert routing tested.
15. `0015-cors-policy.md` — **Proposed.** Explicit CORS allow-list policy for storefront and admin APIs. Becomes Accepted when CORS middleware is deployed and tested.
16. `0016-api-versioning-strategy.md` — **Proposed.** Storefront API versioning approach. Becomes Accepted when the versioning strategy is implemented or a conscious decision not to version is made with documented rationale.

## Proposed ADRs Requiring Action

| ADR | Proposed Since | Blocker | Acceptance Criteria |
|-----|---------------|---------|---------------------|
| 0011 | 2026-05-07 | No caching implementation | Per-route caching implemented and tested |
| 0012 | 2026-05-27 | Backup/monitoring/rollback not yet in production | Full production proof: backup + monitoring + rollback drill |
| 0014 | 2026-05-31 | Provider not selected | Provider selected, integrated, PII scrubber verified |
| 0015 | 2026-05-31 | CORS config not found | CORS middleware deployed, API tested with storefront origin |
| 0016 | 2026-05-31 | No versioning exists | Strategy selected and either implemented or consciously deferred with rationale |

## How to Add a New ADR

1. Copy the format from any existing ADR.
2. Number it sequentially.
3. Set `Status: Proposed`.
4. Add acceptance criteria to "Consequences" so the team knows when to move it to `Accepted`.
5. Add an entry to this README.
6. Update `docs/README.md` if the ADR affects reading-before-edit rules.
