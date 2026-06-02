# Documentation Drift Report

Last updated: 2026-06-02

## Method

Documentation was cross-checked against the current repository files rather than
against memory alone. The audit compared:

- 86 Markdown/text documentation files and 133 documentation-like source files
- routes in `backend/routes/api.php`, `backend/routes/web.php`, and
  `backend/routes/console.php`
- migrations in `backend/database/migrations`
- models, enums, actions, support classes, policies, and observers under
  `backend/app`
- storefront routes, API wrapper, and types under `storefront/src`
- tests under `backend/tests` and `storefront/tests`
- deployment and staging files under `deploy/`

## Confirmed Matches

| Claim | Documentation source | Code/source evidence | Status |
|---|---|---|---|
| Laravel 13, PHP 8.3, Filament 5.6 backend | `README.md`, `docs/ARCHITECTURE.md`, `backend/README.md` | `backend/composer.json` | Matches |
| Next.js 15.5, React 19 storefront | `README.md`, `docs/ARCHITECTURE.md` | `storefront/package.json` | Matches |
| Public storefront API endpoints | `docs/ARCHITECTURE.md`, `docs/STOREFRONT_CART.md` | `backend/routes/api.php` | Matches |
| Health endpoints exist | `docs/PRODUCTION_READINESS.md`, runbooks | `/api/system/health/live`, `/api/system/health/ready`, `system:health` | Matches |
| Backend owns checkout totals | ADR 0005, ADR 0006, domain contracts | `CreateQuickOrder` computes subtotal, shipping, discount, total | Matches |
| Cart supports multiple items | storefront docs and tests | `CheckoutPayload.items`, `CreateQuickOrder::normalizeItems` | Matches |
| Simple products reject variants and variable products require variants | domain contracts, ADR 0013 | `ProductType`, checkout validation, tests | Matches |
| Inventory reservation uses sellable unit | domain contracts, ADR 0013 | inventory actions and `product_variant_id` migrations | Matches |
| Store readiness is domain validation, not deployment proof | domain contracts | `StoreReadinessChecker` | Matches |
| 2FA emergency reset command exists | security docs | `security:reset-two-factor` in `backend/routes/console.php` | Matches |
| Staging proof is dated evidence | root README, evidence index | `docs/evidence/*2026-05-26*`, `*2026-05-28*` | Matches as dated evidence |

## Drift Or Risk Of Drift

| Area | Drift Signal | Impact | Recommended Action |
|---|---|---|---|
| Staging status wording | Some docs say proof exists, older contract wording says real staging is not proven until proof is recorded | Medium | Preserve distinction between dated proof and current live health |
| Monitoring contacts | `docs/operations/INCIDENT_RESPONSE.md` has `[TBD]` contacts | Medium | Fill contacts before production or beta support |
| Audit coverage | `docs/AUDIT_MATRIX.md` has `partial`, `unknown`, and `missing` coverage | High | Treat audit matrix as authoritative before claiming audit completion |
| Error tracking | ADR 0014 exists but provider integration is deferred | Medium | Add implementation runbook after provider selection |
| API versioning | ADR 0016 exists but no versioned API routes are currently present | Medium | Do not claim versioned API is implemented |
| Large sitemap stores | SEO docs note sitemap index is still needed at scale | Low now, high later | Add sitemap index before large-catalog rollout |
| Arabic/English synchronization | `docs/DOMAIN_CONTRACTS_AR.md` is authoritative and summary is separate | Medium | Update both or record summary-only status when changing contracts |
| `Zone.Identifier` files | Untracked metadata files exist | Low | Clean with maintainer approval if desired |

## Undocumented Or Under-Documented Code Areas

- `backend/app/Support/Analytics/TenantOrderAnalytics.php` has less documentation
  than checkout, tenancy, and billing.
- `backend/app/Jobs/Domains/VerifyDomainOwnershipJob.php` and DNS lookup behavior
  should be cross-linked more directly from domain and operations docs.
- `backend/app/Observers/*` are operationally important but not all observer
  side effects are mapped in a single doc.
- Storefront local cart storage behavior is documented, but local storage
  invalidation/versioning rules could be more explicit.
- API versioning strategy exists, but exact implementation triggers and rollout
  mechanics remain future work.

## Code Areas With Strong Documentation Coverage

- Checkout and cart.
- Product variants and variant inventory.
- Tenancy and tenant isolation.
- Security baseline and 2FA.
- Production readiness and staging operations.
- Backup/restore.
- Database migrations.
- Testing strategy.

## Drift Guardrail

Any future change touching these files should update documentation in the same
change or explicitly state why no update is needed:

- `backend/app/Actions/Checkout/*`
- `backend/app/Support/Checkout/*`
- `backend/app/Support/Readiness/*`
- `backend/app/Support/Tenancy/*`
- `backend/app/Models/Concerns/BelongsToTenant.php`
- `backend/database/migrations/*tenant*`, `*inventory*`, `*product_variant*`,
  `*orders*`, `*payments*`, `*subscriptions*`
- `storefront/src/lib/types.ts`
- `storefront/src/lib/api.ts`
- `storefront/src/components/storefront/*checkout*`
- `deploy/staging/*`
- `.github/workflows/*`
