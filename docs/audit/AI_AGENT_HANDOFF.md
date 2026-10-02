# AI Coding Agent Handoff — dz-saas-commerce

**Audit:** 2026-10-01, `main` @ `bd58620`. Read this + `AI_CONTEXT.md` before any change.

## 1. What the project is

Multi-tenant Algerian SaaS commerce monorepo: Laravel 13 + Filament 5.6 backend (`backend/`, PG/Redis/Meilisearch/MinIO/Mailpit, 3 panels `/admin /vendor /support`), Next.js 15 + React 19 + TS storefront (`storefront/`), `deploy/` staging/backup/proxy examples, 3 GitHub workflows, 84 docs incl. binding ADRs (0001–0016; 0011/0012/0014/0015/0016 PROPOSED, not implemented).

## 2. Current architecture (verified, not just documented)

Modular monolith; logic in `app/Actions`, `app/Support`, data objects, policies, small observers; thin controllers/Filament callbacks. 435 `app/` PHP files, 48 migrations, 38 backend test files, 11 public storefront endpoints + 2 health closures, 157 routes total. Money/inventory/subscription authority = backend only; storefront sends items+customer, never totals.

## 3. Immutable business rules (from `docs/ai-first/SYSTEM_INVARIANTS.md` + code)

Tenant rows carry `tenant_id`; every `withoutGlobalScope('current_tenant')` needs same-path tenant filter; `Store` is the documented unscoped exception (use `forTenant()`, fails closed on null); backend owns prices/discounts/shipping/totals/currency/payment/inventory/limits; `ProductType` governs simple (no variant id, product inventory) vs variable (variant id required, variant inventory); every qty/reserved change writes `StockMovement`; manual inventory only via `AdjustInventoryManually`; `AuditLog` append-only; never bypass 2FA; never claim prod-readiness without fresh evidence.

## 4. Security boundaries

Fail-open trap: `BelongsToTenant` adds NO constraint when tenant is null (TEN-001) — always ensure context or explicit filter. Never pass `tenant_id` from request into mass-assignment (TEN-002 — Fillable still contains it; overwrite server-side). `TenantResolver` order host → member-checked header/query/session → first membership. Vendor Filament: `ScopesToCurrentTenant` + `AssignsCurrentTenant` on every resource (keep it 100%). Support panel is intentionally platform-wide. 2FA: do not add bypass flags; reset command is audited but does NOT revoke sessions (AUTH-001 — fix, don't work around).

## 5. Important invariants in code

Checkout TX + `lockForUpdate` (products/variants/inventory/coupon); idempotency keyed tenant+store with 60s keyless window; coupon locked/capped/`used<=limit` CHECK; payments exact-outstanding; invoices `total=subtotal+tax`, `paid<=total`; inventory reserve-first + guarded release/settle/restock; order whitelist in `TransitionOrderStatus` (but shipment sync bypasses — CHK-001, fix by routing through it).

## 6. Known bugs / incomplete / conflicts

Matrix IDs: TEN-001/002/003/004/007, AUTH-001/002/003/004, DB-001 (customer cascade — most destructive), DB-002/003/004/005, CHK-001/002/003, BILL-001/002, API-001/002/003/004, WEB-001, SEC-001, TEST-001/002, OPS-001/002/004/005, DOC-001..007. Doc conflicts: stage-00 has wrong workflow filenames (real: `quality.yml`, `container-images.yml`, `staging-smoke.yml`); "not proven" wording vs dated May proof (read as rule: fresh proof per change); Playwright "6 passed" stale (spec now 9); `AUDIT_MATRIX.md` `unknown` rows (variant CRUD, returns, domain verification) are genuinely unverified.

## 7. Safe files vs special care

SAFE: storefront components/copy/SEO (keep money display-only), docs wording (keep AR authoritative + sync EN summary), runbook examples. SPECIAL CARE: `app/Support/Tenancy/*`, `Models/Concerns/BelongsToTenant.php`, `Models/Store.php`, `Actions/Checkout/*`, `Support/Checkout/*`, inventory/payment/billing actions, policies, migrations (forward-safe only), `routes/*.php`, `storefront/src/lib/api.ts` + `types.ts` (contract), `deploy/staging/*`, `.github/workflows/*`.

## 8. Tests to run

Before: `git status --short --branch` (respect pre-existing changes). After: targeted Pest file(s) for the domain (`php artisan test tests/Feature/<Domain>/...` on testing DB), `php vendor/bin/pint --test`, storefront `tsc --noEmit` (never parallel with build). Full suite + `test:e2e` for cross-domain changes. Baselines from this audit: Pint 574 PASS, tsc clean, TenantFoundation 6/6, QuickCheckout 31/31.

## 9. Implementation order

Roadmap phases 0→11 (`IMPLEMENTATION_ROADMAP.md`): stabilize → tenancy/auth/DB-001 → integrity → commerce → API/storefront → testing → docker → CI → observability → staging proof → prod planning. Do NOT attempt: partial-return APIs (CHK-002), prod traffic (OPS-004/005), new `withoutGlobalScope` without same-path filter, client-side money logic, 2FA bypasses.

## 10. MUST NOT break

Tenant isolation (all 100 scope-removals are load-bearing), money server-side computation, variant buy rules, ledger append-only writes, idempotency semantics, audit append-only, vendor fail-closed scoping, `TenantSwitcher` membership checks, hashed-PII logging, security headers, immutable image tags, no-auto-migrate discipline.
