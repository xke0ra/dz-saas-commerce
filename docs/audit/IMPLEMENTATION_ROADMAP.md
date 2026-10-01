# Implementation Roadmap (dependency-ordered, not severity-sorted)

**Audit:** 2026-10-01, `main` @ `bd58620`. IDs from `MASTER_FINDINGS_MATRIX.md`.

## PHASE 0 — Repository stabilization (P0 action)

- **T-000 push + backup.** WHY: 1 commit ahead unpushed; ignored envs + `docs/audit/` would be lost on reinstall. FILES: repo root, `backend/.env`, `storefront/.env.local`, `deploy/staging/*.env`, `docs/audit/`. DONE WHEN: `git status` clean, `git rev-list --count HEAD...@{u}` = 0, env backups verified restorable.

## PHASE 1 — Critical security and tenancy (P0; blocks beta)

- **T-101 TEN-001 fail-closed scope.** ROOT: null-context returns all tenants. FILES: `backend/app/Models/Concerns/BelongsToTenant.php`, system-context callers. BLOCKS: T-102/103/104. RISK: behavior change in jobs/console — audit each `withoutGlobalScope` caller first (all 100 already traced safe). TESTS: null-context returns 0/throws; full tenancy suite green. DONE WHEN: no query outside explicit context returns cross-tenant rows.
- **T-102 TEN-002 strip `tenant_id` from Fillable.** Depends T-101. FILES: `backend/app/Models/*.php`, `BelongsToTenant.php:28-31`, `AssignsCurrentTenant`. TESTS: mass-assign foreign tenant rejected; vendor create assigns current tenant.
- **T-103 TEN-003/TEN-004 Store + AuditLog scoping.** Depends T-101. TESTS: unscoped store/audit reads in vendor context return 0.
- **T-104 AUTH-001 session revocation on 2FA reset.** FILES: `Support/Auth/TwoFactorAuthentication.php:184-210`. TESTS: old session 401 post-reset; audit row present. DONE WHEN: reset = re-authentication required everywhere.
- **T-105 AUTH-002 register admin 2FA pages.** FILES: `Providers/Filament/AdminPanelProvider.php`. TESTS: admin setup + challenge E2E.
- **T-106 DB-001 customer FK restrict.** ROOT: cascade destroys ledger. FILES: `2026_04_24_020300` follow-up migration + data check. TESTS: customer delete blocked/preserves orders. DONE WHEN: drill delete on staging preserves history.

## PHASE 2 — Database/integrity (P1)

- **T-201 DB-002 order-totals CHECK/clamp.** Depends T-106. **T-202 DB-003 geography FK restrict.** **T-203 DB-004 customer phone uniqueness** (or documented tolerance + test). **T-204 DB-005 status CHECKs + NOT NULL billing period.** TESTS: invalid writes rejected at DB.

## PHASE 3 — Checkout/inventory/orders/payments (P1)

- **T-301 CHK-001 shipment→order via `TransitionOrderStatus`.** TESTS: transition matrix through shipment events. **T-302 CHK-002 partial-return policy** (per-item restock or enforced all-or-nothing + documented). **T-303 CHK-003 hash canonicalization + tracking retry + option-mismatch error + settle error-vs-clamp.** **T-304 BILL-001 invoice-number retry.** **T-305 BILL-002 central gate enforcement** (all restriction checks via `ensureWithinLimit`; scheduler heartbeat). Depends T-101 (tenant context in jobs).

## PHASE 4 — Storefront/API contracts (P2)

- **T-401 API-001 drop-or-wire `q`.** **T-402 API-002 forward replay status.** **T-403 API-003 pagination.** **T-404 WEB-001 loading/error boundaries + API-004 legal include.** TESTS: contract tests backend↔`lib/types.ts`; Playwright rerun (feeds TEST-001).

## PHASE 5 — Billing/subscriptions (covered in T-304/305; remaining LOWs)

- **T-501 usage-counter reconciliation + grace/suspension E2E on staging scheduler.**

## PHASE 6 — Testing and quality (P1/P2)

- **T-601 rerun full backend suite + Playwright; refresh `TESTING_STRATEGY.md` baseline (fixes TEST-001/002 claims).** **T-602 add PHPStan + ESLint to CI.** **T-603 race tests** (oversell, coupon double-use, invoice-number, tracking). Depends T-301/302.

## PHASE 7 — Docker/local reproducibility (P2)

- **T-701 OPS-001 storefront USER+HEALTHCHECK.** **T-702 pin floating tags; align node doc; add root `.gitattributes`.**

## PHASE 8 — CI/CD (P2)

- **T-801 least-privilege `permissions:` in `quality.yml`; refresh default smoke tags (CI-002/003).**

## PHASE 9 — Observability (P1 for prod)

- **T-901 adopt error-tracking (ADR-0014), wire 5xx/checkout/latency/scheduler/mail/TLS/backup-age alerts (OPS-004).**

## PHASE 10 — Staging (P1 for prod)

- **T-1001 fresh external smoke + evidence after phases 1–4; staging rollback drill (OPS-005); live-path restore drill.**

## PHASE 11 — Production readiness (only after 0–10)

- **T-1101 prod env/secrets/TLS/Cloudflare-proxied decision/CSP-HSTS tuning/object-storage/backup-PITR per `PRODUCTION_READINESS.md`; NO prod traffic until gates green with current evidence.**
