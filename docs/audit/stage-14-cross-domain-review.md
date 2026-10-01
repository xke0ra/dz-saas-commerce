# Stage 14: Final Cross-Domain Review

**Audit Date:** 2026-10-01 | Branch `main` | Commit `bd58620db449ac6211584eb0dc0b125ac3656fb8`

Second-pass review for issues individual domain audits may have missed.

## 1. Cross-domain checks

- Tenant resolution consistency: vendor panel (`ResolveTenantFromRequest` + membership re-check), storefront (host-first, fail-closed to 404 unavailable), checkout (pins `tenant_id` from `$store`, never request), idempotency (tenant+store keyed + `lockForUpdate`), billing gates (Trialing/Active/GracePeriod). Consistent; no path found where request-supplied `tenant_id` overrides membership except flagged TEN-007 (support-ticket action trusts caller — admin/support/vendor pages authorize today).
- Money consistency: checkout recomputes price/shipping/discount/total server-side; `RecordOrderPayment` requires exact outstanding; invoice has DB `total=subtotal+tax` + `paid<=total`; coupon capped at subtotal + `used<=limit` CHECK. Gap remains order-level `total=subtotal+shipping-discount` without DB invariant (DB-002) — single app-level computation `CreateQuickOrder.php:117`.
- Status-machine interplay: order whitelist bypassed by shipment sync (CHK-001) is the only cross-domain transition violation found; observer writes history on any status change (compensating visibility, not enforcement).
- Frontend/backend: 11/11 API functions map to live endpoints; types align field-for-field; no client money authority. Edges: dead `q` param (API-001), flattened 201 (API-002), 48/24 truncation without pager (API-003), legal over-fetch (API-004), no loading/error boundaries (WEB-001).
- Test-gap cross-check: concurrency (oversell race, coupon double-use race, invoice-number race BILL-001, tracking race) relies on DB locks/constraints but has no dedicated race tests; `CheckoutAbuseGuard` limits untested under load; scheduler catchup untested.

## 2. Gate reconciliation (stage-03 said PASS WITH FINDINGS; deep audit suggested BLOCKED)

Verdict: **PASS WITH FINDINGS**, with 4 HIGH items as production/beta blockers (TEN-001, TEN-002, AUTH-001, DB-001). Rationale: all 100 `withoutGlobalScope` uses traced to tenant-safe paths; cross-tenant checkout/payment/vendor tests pass (31 checkout tests incl. another-tenant rejections, re-verified green); no demonstrated live exploit. The HIGHs are latent defaults (fail-open scope, fillable `tenant_id`, session survival, customer cascade) — severe IF triggered, but not currently reachable through reviewed entry points. CRITICAL count: 0. This matches stage-03's gate while carrying the stricter remediation list.

## 3. New risks from this pass

- X-001 (LOW): `StoreSettingResource` legal over-fetch + `communes` 1541-row checkout load + unpaginated catalog are the three scale edges; all benign at current size, all need work before large tenants (ADR-0011 proposed, not accepted).
- X-002 (LOW): no genuine TODO/FIXME/HACK in app code (34 grep hits are all Filament `->placeholder()` UI API + i18n strings — false positives); no mock/stub/placeholder production code outside tests. Dead-code surface is clean.
- X-003 (INFO): performance — no N+1 proven; controller eager-loads look deliberate (`product` detail loads 6 relations in one query). No speculative optimization recommended.
