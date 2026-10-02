# Stage 13: Documentation Drift Reconciliation

**Audit Date:** 2026-10-01 | Branch `main` | Commit `bd58620db449ac6211584eb0dc0b125ac3656fb8`

Compares EXPECTED (docs) vs ACTUAL (code) vs TESTED (suites + this audit's runs) vs PROVEN (dated evidence). Existing docs are NOT modified; required changes are listed.

## 1. Confirmed drifts (DOCUMENTATION SAYS vs CODE ACTUALLY DOES)

- DOC-001 (LOW): `docs/audit/stage-00-baseline.md:44-46` names workflows `quality-gates.yml`/`staging-ephemeral.yml`; actual files are `quality.yml`/`staging-smoke.yml` (+`container-images.yml`). STATUS: audit-internal drift. REQUIRED UPDATE: fix stage-00 filenames. Same file claims Node 20 for storefront image; `storefront/Dockerfile` pins `node:24-alpine` (host node v20.20.2 is a different thing). REQUIRED UPDATE: distinguish host runtime vs image runtime.
- DOC-002 (LOW): `docs/TESTING_STRATEGY.md:118` references a `STAGING_SMOKE_PROOF_2026-05-26_AR.md` path that mismatches the actual `docs/evidence/` location. REQUIRED UPDATE: fix path.
- DOC-003 (LOW): `docs/TESTING_STRATEGY.md:35` + `docs/DOMAIN_CONTRACTS_SUMMARY.md:118` ("staging not proven") read as current status though dated proof exists; `DOCUMENTATION_AUDIT_REPORT.md:49-51` already flags this. Read as rule, not status. REQUIRED UPDATE: reword to "fresh proof required per change".
- DOC-004 (LOW): root `README.md` "external staging proven as of 2026-05-26" is accurate only as dated proof, not live health. REQUIRED UPDATE: add "historical; rerun smoke before relying".
- DOC-005 (LOW): broad "audited" language in adjacent docs vs conservative `docs/AUDIT_MATRIX.md` (`implemented/partial/unknown/missing`); variant CRUD + returns/security-admin rows `unknown`, domain verification `missing`, checkout `AuditLog` `partial` (history only). REQUIRED UPDATE: consult matrix before claiming coverage; fill `unknown` rows.
- DOC-006 (INFO): ADR-0011/0012/0014/0015/0016 are Proposed with explicit acceptance blockers (`docs/adr/README.md:35-43`) — no drift as long as nobody claims them implemented. CORS has no `config/cors.php` customization; no versioned routes; no error-tracking provider.
- DOC-007 (LOW): `docs/operations/INCIDENT_RESPONSE.md` contacts `[TBD]`; AR/EN contract sync manual (`DOMAIN_CONTRACTS_AR.md` authoritative); no CI heading/summary/evidence-freshness gates.

## 2. Code-vs-docs agreements verified (no drift)

Backend source-of-truth for money (no client totals in checkout payload — `quick-order-form.tsx:201-212` sends items+customer only); simple/variable variant rules enforced in request AND action AND covered by 31 green checkout tests; inventory ledger append-only with `StockMovement` on every mutation path reviewed; tenant scope fail-closed in vendor Filament + storefront re-scoping; idempotency semantics as documented (201 vs 200 replay — except the Next proxy flattening, API-002).

## 3. Tested-vs-documented

`docs/TESTING_STRATEGY.md:28` "292 passed" is dated; this audit re-verified 37 tests green (TenantFoundation 6, QuickCheckout 31) + Pint 574 + tsc clean. Full 38-file suite + Playwright 9 tests: UNVERIFIED current (not rerun).
