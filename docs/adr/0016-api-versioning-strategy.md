# ADR 0016: API Versioning Strategy

Date: 2026-05-31

Status: Proposed

## Context

The public storefront REST API (`/api/storefront/...`) currently has no versioning. Any breaking change requires all connected storefronts to update simultaneously.

Current state:

- All storefront endpoints are at `/api/storefront/{store}/...` with no version prefix.
- No API changelog exists.
- The storefront is the only consumer (no third-party API clients yet, per ADR 0009).
- The platform is pre-production with no paying customers.

Implications of no versioning:

- Any breaking change (renamed field, changed response shape, removed endpoint) requires a coordinated deployment of both backend and storefront.
- If a third-party API access feature is added in the future (`PlanFeatureKey::ApiAccess` exists), merchant-built integrations would break on any API change.

## Decision

[TO BE DECIDED — select a strategy before first paying customer]

Options:

| Option | Path | Trade-offs |
|--------|------|-----------|
| **URL prefix versioning** | `/api/v1/storefront/` | Simple, explicit, industry standard; requires route duplication for new versions |
| **No versioning (deliberate)** | `/api/storefront/` | Acceptable if: API consumers are internal only AND breaking changes always deploy backend+storefront together |
| **Header versioning** | `Accept: application/vnd.dzsaas.v1+json` | Less discoverable; harder to test |

**Recommendation for current stage:** Use URL prefix versioning (`/api/v1/storefront/`) when third-party API access is enabled. Until then, a conscious decision not to version is acceptable, provided it is documented here.

## Consequences

If URL prefix versioning is chosen:

- All current `/api/storefront/...` routes become `/api/v1/storefront/...`.
- Old routes can be redirected or kept as aliases during a transition period.
- `storefront/src/lib/api.ts` must be updated to use the versioned base URL.
- New API features go under `/api/v1/storefront/` by default; breaking changes require `/api/v2/`.

If no versioning is chosen deliberately:

- Document the constraint: no third-party API access allowed until versioning is implemented.
- Any breaking change requires synchronized backend + storefront deployment.
- This decision must be revisited before enabling `PlanFeatureKey::ApiAccess`.

This ADR moves to `Accepted` when:

1. A specific versioning strategy is chosen.
2. Either the routes are versioned, or a documented policy prevents third-party API access until they are.
3. The decision is reflected in `docs/ARCHITECTURE.md` and storefront API documentation.
