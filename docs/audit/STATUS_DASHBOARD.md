# Status Dashboard — dz-saas-commerce @ `bd58620` (2026-10-01)

No numeric scores. "Production ready" is not claimed anywhere without current evidence.

| Subsystem | Status | Note |
|---|---|---|
| Tenancy isolation (current paths) | CONFIRMED IMPLEMENTED | 100/100 scope-removals traced safe; cross-tenant tests green (re-verified) |
| Tenancy defaults (fail-closed, fillable) | PARTIAL (HIGH) | TEN-001, TEN-002, TEN-003, TEN-004 |
| Authentication / sessions | PARTIAL (HIGH) | AUTH-001 sessions survive reset; AUTH-002 admin 2FA pages |
| Authorization / policies | PARTIAL | 30 policies good; 13 models lack policies (AUTH-004 LOW) |
| Catalog / variants | CONFIRMED IMPLEMENTED | ADR-0013 built + tested |
| Inventory ledger | CONFIRMED IMPLEMENTED | Reserve/release/settle/restock + movements on all paths |
| Checkout / idempotency / coupons | CONFIRMED IMPLEMENTED | 31/31 tests green incl. races-by-lock, re-verified |
| Orders lifecycle | PARTIAL | CHK-001 shipment bypass |
| Payments (manual/COD) | CONFIRMED IMPLEMENTED | ADR-0009 scope; no gateway by design |
| Shipping / returns | PARTIAL | Works; CHK-002 partial-return caveat; tracking retry gap |
| Billing / subscriptions / gates | PARTIAL | Lifecycle + gates work; BILL-001/002 races/future-caller gaps |
| Public API ↔ storefront contract | PARTIAL | 11/11 mapped, no leaks; API-001/002/003 edges |
| Storefront UX/SEO | PARTIAL | Complete + no dead code; WEB-001 loading/error boundaries missing |
| Database integrity | PARTIAL (HIGH) | Strong money/composite constraints; DB-001 customer cascade BROKEN |
| Backend tests | PARTIAL | 38 files exist; 37 tests re-verified green; full suite UNVERIFIED current |
| Frontend tests | UNVERIFIED | 9 E2E exist, baseline stale, not rerun; no unit framework |
| Static analysis / lint | PARTIAL | Pint + tsc green (verified); no PHPStan/Psalm/ESLint |
| Docker / local reproducibility | CONFIRMED IMPLEMENTED | Boots verified; hardening LOWs (root storefront, floating tags) |
| CI/CD | PARTIAL | Comprehensive YAML; no run executed in audit; manual dispatch by design |
| Staging deployment | PARTIAL | Compose + runbooks exist; proof dated May 2026 (historical) |
| Backup / restore | PARTIAL | Staging automated + tmp-DB drill PASS (historical); live-path UNVERIFIED |
| Monitoring / alerting / error tracking | MISSING | No provider; alerts largely absent |
| Rollback | UNVERIFIED | Documented, never drilled |
| Production readiness | MISSING | No prod target; correctly not claimed |
| Documentation accuracy | PARTIAL | Excellent + honest; 7 LOW drifts (stage-13) |
| AI-agent readiness | PARTIAL | Strong maps/invariants; TEN-001/002 traps must be fixed or heeded |
| Dead code / mocks in prod | CONFIRMED IMPLEMENTED (clean) | Zero genuine TODOs; no prod stubs |
| Linux migration | SAFE WITH ACTIONS | Checklist in `LINUX_MIGRATION_READINESS.md` |

**Counts:** CRITICAL 0, HIGH 4, MEDIUM 10, LOW 17, INFO 3. **Gate verdicts:** Security PASS WITH FINDINGS (production-blocked by 4 HIGHs), Database FAIL on DB-001, Quality/Repro/CI PASS WITH FINDINGS, Operations staging-grade only.
