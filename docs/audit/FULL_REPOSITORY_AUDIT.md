# Full Repository Audit — dz-saas-commerce

**AUDIT_COMMIT_SHA:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`
**AUDIT_BRANCH:** `main` (1 commit ahead of `origin/main`, unpushed)
**AUDIT_DATE:** 2026-10-01
**Remote:** `https://github.com/xke0ra/dz-saas-commerce.git`
**Working tree:** clean except untracked `docs/audit/` (this audit)
**Scope:** 837 tracked files; stages `stage-00`–`stage-17` in `docs/audit/`; matrix `MASTER_FINDINGS_MATRIX.md` (34 rows: 0 CRITICAL, 4 HIGH, 10 MEDIUM, 17 LOW, 3 INFO).

## 1. Executive Summary

A genuinely well-engineered modular monolith with honest documentation. Tenancy/auth/checkout/money/inventory are implemented carefully: all 100 `withoutGlobalScope('current_tenant')` uses traced to tenant-safe paths, checkout is transactional with row locks + idempotency + variant discipline, money has DB CHECKs, and the highest-risk suites re-verified green in this audit (TenantFoundation 6/6, QuickCheckout 31/31 incl. cross-tenant rejections). No confirmed live CRITICAL vulnerability. Four HIGH latent defects block beta/production with real money/data: fail-open tenant scope default (TEN-001), fillable `tenant_id` (TEN-002), 2FA reset without session revocation (AUTH-001), customer-cascade destroying financial history (DB-001). Operations/monitoring/rollback are staging-documented but production-unproven. Linux migration is SAFE WITH REQUIRED PRE-MIGRATION ACTIONS (push ahead commit, back up ignored env files, install Node 24/pnpm 11).

## 2. Audit Scope

Sections 1–43 of the audit brief, executed as stages 0–18 (reports `stage-00`–`stage-14`, `stage-17`; stages 15/16/18 are this matrix, the roadmap, and this report set). Read-only except new files under `docs/audit/`. Verified by execution: Pint 574 PASS, tsc clean, composer valid, route:list 157 lines, 37 Pest tests green. Full 38-file suite + Playwright E2E not rerun (UNVERIFIED, not failed).

## 3. Repository Baseline

Root `main` @ `bd58620` ("remove Windows Zone.Identifier artifacts"), ahead of origin by 1. 694 MB on disk (vendor + node_modules). Subsystems: `backend/` 654 tracked files (Laravel 13 modular monolith), `storefront/` 62 (Next.js 15), `docs/` 75, `deploy/` 23, `scripts/` 7, `.devcontainer/` 5, `.github/` 3, root 8. Secret-bearing files all ignored: `backend/.env`, `storefront/.env.local`, `deploy/staging/*.env`.

## 4. Technology Stack

PHP 8.3 (host 8.3.6), Laravel 13 (`^13.0`), Filament 5.6, Scout 11.1 + Meilisearch PHP 1.16, S3 Flysystem, Pest 4.6/PHPUnit 12.5.12/Pint 1.27. Next 15.5.18, React 19, TS 5.7 strict, Tailwind 3.4.17, RHF 7.64 + Zod 3.25, Playwright 1.59.1, pnpm 11.1.2 (packageManager-enforced; NOT installed on this host). Infra: Postgres 17, Redis 7, Meilisearch v1.12, MinIO/MinIO-mc + Mailpit latest (floating), nginx 1.27 edge, Trivy 0.70.0. CI: `quality.yml` (hygiene/backend/storefront/docker/e2e), `container-images.yml` (manual dispatch, GHCR), `staging-smoke.yml` (manual, staging env contract). Inconsistencies: `node:24-alpine` images vs `node:24-bookworm` in `verify-docker.sh`; backend Vite Tailwind 4 vs storefront Tailwind 3 (separate apps, harmless); May-pinned default smoke tags.

## 5. Architecture

Modular monolith per ADR-0001; shared-DB tenancy (ADR-0002); Laravel+Filament backend (ADR-0003); separate Next.js storefront (ADR-0004); backend money source-of-truth (ADR-0005/0006); manual payments first (ADR-0009); Algerian shipping (ADR-0010); variants/inventory design (ADR-0013, accepted). Proposed/not-accepted: caching-0011, topology-0012, error-tracking-0014, CORS-0015, versioning-0016. Business logic in Actions/Support/policies/observers; thin controllers/Filament callbacks — verified in checkout/billing/inventory paths. Filament panels: `/admin` superadmin, `/vendor` membership+`stores.view` with tenant resolver + fail-closed scoping, `/support` platform-wide tickets by design. No architectural violations found in reviewed paths except CHK-001 (shipment→order direct status write).

## 6. Tenancy

Shared PG + `tenant_id` + `BelongsToTenant` (`current_tenant` scope) + `CurrentTenant` scoped singleton + `TenantResolver` (host → X-Tenant-ID/query+session gated by membership → first membership) + `TenantSwitcher` (membership-checked) + `ResolveTenantFromRequest` (set/forget in `finally`, vendor panel only). 100 `withoutGlobalScope` uses, ALL traced safe (re-scoped to `$store->tenant_id`/explicit tenant, or platform-intentional). 33 models use the trait. Gaps: TEN-001 fail-open on null tenant (HIGH), TEN-002 fillable tenant_id (HIGH), TEN-003 Store no global scope (MEDIUM), TEN-004 AuditLog unscoped (MEDIUM), TEN-007 caller-trusted tenant in 2 actions (MEDIUM). Vendor Filament 100% `ScopesToCurrentTenant` fail-closed + `AssignsCurrentTenant` overwrite. Gate: PASS WITH FINDINGS (reconciled in stage-14; no live exploit demonstrated, cross-tenant tests green).

## 7. Authentication

Filament-native session auth on 3 panels + `AuthenticateSession`; password broker configured (Filament default flow); `MustVerifyEmail` disabled (document decision). 2FA TOTP: encrypted secret, bcrypt recovery codes (single-use), TOTP window 8 (generous), replay cache 9 min, per-user 5-attempt limiter, user-bound session keys, setup/challenge enforcement per panel. Gaps: AUTH-001 reset keeps sessions alive (HIGH); AUTH-002 admin 2FA pages unregistered (MEDIUM/BROKEN — verify at runtime); AUTH-003 invite route unthrottled, recovery-code consumption unaudited (LOW).

## 8. Authorization

30 policies registered, all tenant-shaped (`hasCurrentTenantPermission` + per-instance check), `Gate::before` superadmin blanket-allow except audit mutation; `AuditLogPolicy` immutable + superadmin-view; `OrderPolicy` create/delete false (orders only via checkout, no hard delete). 13 child/system models lack policies (AUTH-004, LOW — parent-gated today). Throttles: storefront 120/1, checkout 20/1, track 60/1, switch-tenant 30/1 + `CheckoutAbuseGuard` IP/phone/store limits.

## 9. Security

No hardcoded secrets/keys; no open redirects (`pullIntendedPanelUrl` rejects 2FA URLs; invite redirects hardcoded); no raw PII logging (hashed IP/phone in idempotency/abuse logs); `tenant_id`+cost never serialized by storefront resources (verified `ProductResource:223` internal-only comparison); CSP/nosniff/SAMEORIGIN/referrer/Permissions-Policy global + HSTS-on-secure (SEC-001 LOW: Filament-required `unsafe-inline/eval`, permissive `connect-src` — tighten pre-prod). `TRUSTED_PROXIES=*` only acceptable loopback-bound (documented). Uploads: restrict MIME/size, off-public-path, signed URLs, per-tenant paths (documented).

## 10. Database

48 migrations. Money strong: unsigned + CHECKs everywhere; invoices strongest (`total=subtotal+tax`, `paid<=total`); coupons (`used<=limit`, %/fixed guards); inventory (`qty/reserved>=0`, backorder CHECK). Composite same-tenant FKs block cross-tenant linkage; partial uniques for inventory sellable units, wilaya-wide rates, single-current subscription, one-coupon-per-order. Gaps: DB-001 customer cascade (HIGH/BROKEN — fix to restrict), DB-002 no order-totals invariant (MEDIUM), DB-003 geography cascade on rates (MEDIUM), DB-004 phone uniqueness app-only (LOW), DB-005 app-only status enums + nullable-period edge (LOW). Gate: FAIL on DB-001 only; otherwise strong.

## 11. Checkout

`QuickCheckoutRequest` (DZ phone regex, commune∈wilaya, duplicate/mix guards, qty caps) → `CreateQuickOrder` in TX with `lockForUpdate` on products/variants/inventory/coupon → server-side price/shipping/coupon/payment → `reserved` increment + `Reserved` movement + order/items/history/payment(+redemption) → idempotent 201/200. Variant discipline complete (variable-requires-variant, simple-rejects-variant, ownership/active checks) and test-proven (31 green). Coupon locked + capped + `used<=limit` CHECK. Suspended/past-due tenants fail closed at `resolveStore`. Edges: CHK-003 (hash item-order, tracking retry, silent option drop, settle clamp).

## 12. Inventory

Sellable-unit rows (simple=product, variable=variant), `available = qty - reserved` (`track_quantity`), reserve-first never deduct, lifecycle actions Release/Settle/Restock all metadata-guarded + movement-written; manual adjustment ONLY via `AdjustInventoryManually` (permission + reason + movement + audit). Ledger verified CONFIRMED IMPLEMENTED. CHK-002 partial-return caveat.

## 13. Orders

Whitelist transitions + `lockForUpdate` + idempotent same-status + history via observer on ANY status change. Bypass: CHK-001 shipment sync writes directly. Cancelled/Returned release, Delivered settles, Refunded relies on flags (correct via guarded actions).

## 14. Payments

COD-first per ADR-0009 (no gateway — documented, not missing). `RecordOrderPayment` exact-outstanding + oldest-Pending reuse; `RefundOrderPayment` order-Paid gate, all-or-nothing (PAY-001 limitation); `MarkOrderPaymentFailed` blocks Paid/Refunded. No double-pay/overpay path found. Gateway abstraction is a documented future decision, not a hidden stub — no mock payment code in production paths.

## 15. Shipping

Companies/rates (wilaya + optional commune, partial uniques), delivery type, shipment lifecycle with one-open guard + auto-Pack, failed-delivery reasons, tracking loop (CHK-003 retry gap), `FailedDelivery→Cancelled` interplay acceptable. Algerian geography: 58 wilayas/1541 communes active; 69-reform explicitly deferred (ADR-0007).

## 16. Returns

Whitelist + full restock (idempotent via `restocked_at`) + refund chain; correct for all-or-nothing flows incl. pre/post-settlement. Partial returns unsupported — CHK-002 guard before exposing per-item APIs.

## 17. Billing

Subscribe (lock + replace + optional invoice, free-plan skip) → invoice (period-dedup, `INV-Ym-######`) → record/confirm (overpay guard, paid/balance, renewal shift, suspension-matched reactivation)/reject (pending-only + reason) → daily `billing:process` (overdue→PastDue, renewal window, reminders deduped, grace→suspend, config-gated). Gates `Trialing/Active/GracePeriod`; checkout enforces order limit + coupon plan inclusion. Gaps: BILL-001 invoice-number race (LOW), BILL-002 no central gate enforcement for future callers (LOW).

## 18. Storefront

App Router, all `force-dynamic`; 8 routes + 4 API proxies; no `middleware.ts` (per-request host resolution + `DEFAULT_STORE_IDENTIFIER` fallback — by design). Zero dead components (all imports verified). No client money authority (sends items+customer only; cart math is qty + display formatting). Consistent `StoreUnavailable` fallbacks + empty states; missing `loading.tsx`/`error.tsx` (WEB-001). SEO solid (per-store metadata, sitemap/robots, noindex on cart/track/search, secure headers, `poweredByHeader:false`).

## 19. API

11 public endpoints inventoried (geography ×2, resolve, home, products, product, categories, category, search, checkout, track-order) + 2 health closures. No auth (public by design), no `tenant.resolve` (per-store resolution instead — correct). No `tenant_id`/cost/internal leakage in any resource. Validation asymmetries noted (track-order phone lenient by design). Contract edges API-001/002/003/004.

## 20. Testing

38 backend files across all domains; 9 Playwright E2E (mock backend). This audit: 37 tests green on the two riskiest files, Pint 574, tsc clean. Full suite + E2E UNVERIFIED current. No PHPStan/Psalm/ESLint, no frontend unit tests (TEST-002).

## 21. Docker

Reproducible; backend image exemplary (non-root + HEALTHCHECK); storefront needs USER + HEALTHCHECK (OPS-001); pin floating tags (OPS-002). Verified booting with live containers on audit host.

## 22. CI/CD

Comprehensive quality gates + manual image/staging dispatch (safety by design); GHCR immutable tags; staging env contract enforced. CI-002/003: no least-privilege permissions block; stale default smoke tags; no run executed in audit.

## 23. Deployment

Staging-grade complete (compose, edge, queue/scheduler services, runbooks); prod target does not exist; no auto-migrate (operator-run, correct). DEP/OPS-005 rollback undrilled.

## 24. Backups

Staging Postgres automated + drill-proven to tmp DB (May 2026, historical). Prod PITR/encryption/offsite, recurring cadence, object-storage restore: UNVERIFIED/documented.

## 25. Monitoring

Health live/ready implemented; stderr logging; runbook matrix honest (liveness/readiness/DB/Redis/storage done; 5xx/checkout/latency/scheduler/mail/TLS/backup-age + provider missing). ADR-0014 unadopted. OPS-004 blocks production, not migration.

## 26. Documentation

75+ docs, AR-authoritative contracts, binding ADRs, AI-first layer — best-in-class honesty (dated evidence labeled historical; matrix conservative). 7 LOW drifts listed in stage-13; none affect safety if stage-13 consulted.

## 27. AI-Agent Readiness

Can continue safely IF it reads `AI_CONTEXT.md` + `TENANCY_RULES.md` + `SECURITY_BASELINE.md` + this audit's handoff: invariants explicit, ownership mapped, critical paths traced, test commands known. Risks: fail-open scope default + fillable tenant_id are exactly the traps an agent would fall into (TEN-001/002 must be fixed or prominently warned — done in `AI_AGENT_HANDOFF.md`). Blocked: claiming prod readiness; partial-return APIs; new `withoutGlobalScope` without same-path tenant filter.

## 28. Performance

No proven N+1; eager loads deliberate. Three scale edges: 1541-row `communes` per checkout load (ADR-0011 target), legal over-fetch, 48/24 truncation. No action needed at current scale.

## 29. Technical Debt

Zero genuine TODOs; no production mocks/stubs (mockery is dev-only; Playwright mock-backend is test-only). Debt = the findings matrix: 4 HIGH + 10 MEDIUM + hardening LOWs. No hidden prototype code.

## 30. Critical Risks

Top: DB-001 (data loss on customer delete), TEN-001/TEN-002 (cross-tenant primitives), AUTH-001 (session survival), CHK-001 (transition bypass), OPS-004/005 (blind + undrilled ops). None is currently reachable as a demonstrated exploit; all are one bad change away — hence P0/P1.

## 31. Recommended Fix Sequence

Phase 0 stabilize (push commit, back up envs) → Phase 1 TEN-001/002/003/004 + AUTH-001/002 + DB-001 → Phase 2 DB-002/003/004 + CHK-001/002 → Phase 3 API-001/002/003 + BILL-001/002 + CHK-003 → Phase 4 TEST-001/002 + DOC fixes → Phase 5 OPS-001/002 + CI-002/003 → Phase 6 OPS-004/005 + rollback/restore drills → staging re-proven → prod planning. Full order in `IMPLEMENTATION_ROADMAP.md`.

## 32. Linux Migration Readiness

SAFE WITH REQUIRED PRE-MIGRATION ACTIONS. Checklist: push `bd58620`; back up `backend/.env`, `storefront/.env.local`, `deploy/staging/*.env`; export precious dev-DB rows or accept reseed; commit/copy `docs/audit/`; fresh Linux: Docker, PHP 8.3, Composer, Node 24, pnpm 11.1.2; `docker compose up -d`; copy env examples; `migrate --seed`; verify Pint/tsc/tests. Details in `LINUX_MIGRATION_READINESS.md` + gate `stage-17`.

## 33. Final Engineering Status

Partially-built, security-conscious commerce platform: commerce core (catalog/variants/inventory/checkout/orders/payments COD/shipping/returns/billinggates/storefront/API) CONFIRMED IMPLEMENTED and partly re-verified; production operations UNVERIFIED/MISSING; 4 HIGH defects must precede beta/money/data. NOT production-ready; IS migration-safe with checklist; IS a sound base for continued development under the handoff rules.
