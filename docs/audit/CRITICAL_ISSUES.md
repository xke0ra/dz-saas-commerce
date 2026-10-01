# Critical Issues — must fix before beta / production / real money / real data

**Audit:** 2026-10-01, `main` @ `bd58620`. Full evidence in `MASTER_FINDINGS_MATRIX.md`.

## BLOCKER (fix before any beta with real tenants, money, or customer data)

1. **DB-001 (HIGH, BROKEN): customer delete destroys financial history.** `orders.customer_id cascadeOnDelete` (`backend/database/migrations/2026_04_24_020300_create_orders_table.php:22`) cascades customer → orders → items/payments/shipments/returns. Fix: `restrictOnDelete` (or nullable + `nullOnDelete`) with data migration + test.
2. **TEN-001 (HIGH): fail-open tenant scope.** `BelongsToTenant.php:15-20` adds no constraint when `CurrentTenant` is null → cross-tenant reads. Fix: fail closed + explicit system-context opt-out + regression test.
3. **TEN-002 (HIGH): `tenant_id` mass-assignable + preserved on create.** ~30 models Fillable + `BelongsToTenant.php:28-31`. Fix: remove from Fillable; server-side force-fill; test foreign-`tenant_id` rejection.
4. **AUTH-001 (HIGH): 2FA reset leaves sessions alive.** `TwoFactorAuthentication.php:184-210`. Fix: delete user sessions + cycle `remember_token` + `forgetSession`; test old-session 401.

## HIGH PRIORITY (fix before production; may ride a beta with mitigations documented)

5. **TEN-003 (MEDIUM): `Store` has no global scope** (`Store.php`). Add scope or audited wrapper.
6. **TEN-004 (MEDIUM): `AuditLog` unscoped + unscoped admin table queries** (`AuditLogsTable.php:92,105`). Scope or platform-guard.
7. **AUTH-002 (MEDIUM/BROKEN): admin 2FA pages unregistered** (`AdminPanelProvider.php:39-41`). Register + runtime-verify (possible redirect loop today).
8. **CHK-001 (MEDIUM): shipment sync bypasses order transition whitelist** (`TransitionShipmentStatus.php:123-146`). Route through `TransitionOrderStatus`.
9. **DB-002 (MEDIUM): no DB order-totals invariant.** Add CHECK/clamp + test.
10. **OPS-004 (MEDIUM): no prod monitoring/alerting/error-tracking.** Adopt ADR-0014 provider + alerts before prod.
11. **OPS-005 (MEDIUM): rollback/restore never drill-proven on live path.** Staging rollback drill + evidence before prod.
12. **TEST-001 (MEDIUM): E2E baseline stale, full suite not rerun.** Rerun full backend suite + `test:e2e`, refresh docs baseline.

## IMPORTANT (tracked; fix in hardening sprints)

TEN-007, AUTH-003/004, DB-003/004/005, CHK-002/003, PAY-001 (limitation), BILL-001/002, API-001/002/003, WEB-001, SEC-001, TEST-002, OPS-001/002, DOC-001..007. See matrix for owners and tests.

## Explicitly NOT blocking

Playwright mock-backend (test-only, correct); floating dev tags (pin at hardening); Tailwind 3/4 split (separate apps); `SESSION_ENCRYPT=false` default (set true if session stores sensitive data); legal over-fetch + 48/24 truncation + 1541-row communes (scale edges, fine now).
