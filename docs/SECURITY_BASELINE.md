# Security Baseline

Last updated: 2026-05-12

This document defines the minimum security posture expected as the platform moves toward commercial launch.

## Security Principles

- Tenant isolation is a security boundary.
- The frontend is never trusted for money, inventory, discounts, shipping, payment, or subscription limits.
- Authorization must be explicit through policies, gates, and tenant permissions.
- Admin and vendor dashboards are sensitive operational surfaces.
- Auditability is required for important business actions.

## Current Security Controls

Currently present:

- Filament panel access through `User::canAccessPanel()`
- platform roles: super admin and platform support
- tenant roles and permissions through internal enums and tenant pivot data
- policy registration in `App\Providers\AppServiceProvider`
- tenant current context through scoped `CurrentTenant`
- tenant global scope through `BelongsToTenant`
- checkout rate limiting in `backend/routes/api.php`
- checkout abuse guard by IP, phone, and store
- checkout idempotency records by tenant/store/key/request hash
- checkout duplicate-window replay for repeated submissions without an idempotency key
- duplicate cart product IDs rejected at request validation and quick order creation
- public storefront throttling
- public storefront `StoreResource` omits internal `tenant_id`
- `Store::forTenant(null)` fails closed instead of returning every store
- repository secret hygiene check in CI through `scripts/security/secret-hygiene.sh`
- backend security headers middleware
- storefront security headers through Next.js `headers()`
- readiness fails in production when `APP_DEBUG=true` or `APP_KEY` is missing
- trusted proxy config through `TRUSTED_PROXIES`
- test coverage for forwarded HTTPS only from configured proxies
- cross-tenant database constraints for important relationships
- audit log domain foundation

Current important gaps:

- no documented 2FA setup
- no full session/device management
- CSP baseline is intentionally broad for Filament/Livewire/storefront compatibility and still needs production tightening after browser/e2e validation
- backup/restore runbook and automation examples exist, but no deployed backup schedule or executed staging restore drill is recorded yet
- no completed secrets rotation procedure
- no formal vulnerability review workflow beyond the current green dependency audit and secret hygiene baseline
- no production monitoring/error tracking integration or alert routing
- production `.env.production.example` files exist, but real secret management and rotation are not implemented yet
- `Store` remains a documented exception to the global tenant scope; new store queries still need explicit review

## Authentication

Required before production:

- 2FA for super admins
- 2FA for tenant owners where practical
- clear password reset configuration
- session timeout policy for admin/vendor panels
- device/session visibility or revocation for sensitive accounts

## Authorization

Rules:

- All Filament resources must be protected by policies.
- Platform admin resources should normally require super admin.
- Support panel resources should allow platform support only where intended.
- Vendor resources must require tenant membership and tenant permission.
- Super admin bypass must not bypass explicit deny rules for dangerous immutable records such as audit log mutation.

When adding a new model:

1. create policy
2. register policy in `AppServiceProvider`
3. add tests for allowed and denied roles

## Tenant Isolation

Tenant isolation must be enforced at multiple layers:

- request tenant resolution
- Eloquent global scopes
- explicit tenant filters when bypassing scopes
- policies
- database constraints
- tests

Any `withoutGlobalScope('current_tenant')` must be reviewed as security-sensitive.

## Public Storefront API

Public endpoints must:

- validate all input
- throttle abuse-prone routes
- return 404 for unavailable stores
- avoid leaking another tenant's records
- avoid exposing internal IDs where unnecessary
- never trust client-calculated totals

Current public throttles:

- storefront group: `throttle:120,1`
- checkout: `throttle:20,1`
- track order: `throttle:60,1`
- checkout abuse guard:
  - IP scoped limit
  - phone scoped limit
  - store scoped limit

Checkout idempotency:

- `Idempotency-Key` is supported by Laravel checkout.
- Next.js quick order sends `Idempotency-Key` through the route proxy.
- Reusing the same key with the same payload returns the existing order.
- Reusing the same key with a different payload returns conflict.
- Logs use phone/IP hashes for suspicious checkout events.
- Expired checkout idempotency records can be pruned with `php artisan checkout-idempotency:prune`.
- The scheduler runs `checkout-idempotency:prune` daily at 03:00.

These limits should be revisited before production using real traffic expectations.

## Checkout Security

Checkout must always calculate on the backend:

- product availability
- price
- subtotal
- shipping fee
- discount
- inventory reservation
- total
- payment record

Checkout must run critical changes in a transaction and lock inventory/product rows where needed.

Before broad beta, add:

- operational metrics for rate-limited checkout attempts
- real integration e2e for idempotent storefront checkout replay

## Data Protection

Sensitive data currently includes:

- customer names
- phone numbers
- addresses
- order notes
- payment proof metadata later
- merchant legal/commercial fields later

Required direction:

- do not log sensitive payloads unnecessarily
- avoid exposing full customer data to unauthorized staff
- define export permissions before adding exports
- define retention policy for support tickets, logs, and customer data

## Secrets

Rules:

- no hardcoded passwords or tokens in application code
- local sample credentials may live in local docker/dev examples only and must be clearly treated as dummy local values
- production secrets must come from environment or secret manager
- Meilisearch, S3, mail, payment, and shipping credentials must be rotatable
- `.env` and `.env.local` must not be committed or included in clean delivery bundles
- `.env.example` and `.env.testing.example` must contain only placeholders or local-only dummy values

Before launch, document:

- secret inventory
- rotation process
- production `.env` template without values

2026-05-07 hygiene baseline:

- root `.gitignore`, `backend/.gitignore`, and `storefront/.gitignore` now exclude local env files and generated dependency/build/test artifacts.
- `backend/.env.testing.example` exists for testing setup.
- `backend/.env.production.example` and `storefront/.env.production.example` exist with placeholders only.
- `docs/LOCAL_DEVELOPMENT.md` documents local-only dummy credentials and clean workspace rules.

2026-05-09 hygiene baseline:

- `scripts/security/secret-hygiene.sh` checks tracked files for forbidden local env files, private key material, generated dependency/build artifacts, and high-confidence token patterns.
- `scripts/release/clean-export-check.sh` builds a temporary clean export archive and verifies it does not include forbidden local/generated files or high-confidence secret patterns.
- `.github/workflows/quality.yml` runs both checks as `Repository Hygiene`.
- `docs/PRODUCTION_READINESS.md` documents the first production runbook baseline.
- A scan excluding local `.env` files found local dummy credentials in `docker-compose.yml` and `.env.example`; these are acceptable only for local development and must be rotated outside local use.

2026-05-12 security verification:

- `scripts/security/secret-hygiene.sh` passed.
- `scripts/release/clean-export-check.sh` passed and produced a clean export archive of `1.8M`.
- `composer audit --no-interaction` reported no backend advisories.
- `pnpm audit --audit-level moderate` reported no known storefront vulnerabilities after updating Next to `15.5.18`.
- The same audit was proven inside GitHub Actions on PR #1 / run `25743248405`, and main branch protection now requires the Quality Gates checks.

## Audit Logging

Actions that require audit logs:

- tenant created or suspended
- subscription changed
- invoice/payment confirmed or rejected
- order status changed
- shipment status changed
- product deleted
- staff invited or permissions changed
- support ticket status/assignee changed
- custom domain verified or removed

Audit logs should include:

- actor
- tenant when relevant
- auditable entity
- event name
- before/after metadata where useful
- timestamp

Audit logs should be append-oriented. Mutation/deletion must be strongly restricted.

## Headers And Browser Security

Implemented baseline:

- Laravel global middleware adds:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Permissions-Policy` denying camera, microphone, geolocation, and payment
  - a broad CSP compatible with the current Filament/Livewire/storefront stack
  - HSTS only for HTTPS requests
- Next.js storefront applies the same baseline headers through `next.config.ts`.
- `backend/tests/Feature/Security/SecurityHeadersTest.php` covers the backend smoke behavior.

Before production, tighten and verify:

- HTTPS-only cookies
- secure session settings
- CSRF protection on dashboard forms
- CSP with fewer broad allowances after browser/e2e validation
- reverse proxy behavior does not remove or contradict application headers
- HSTS behavior behind the real TLS/reverse proxy layer

## File Uploads

Before enabling broader uploads:

- restrict MIME types
- restrict file size
- store outside public path unless intentionally public
- scan or validate files where possible
- use signed/private URLs for sensitive files
- separate tenant paths in object storage

## Operational Security

Required before commercial launch:

- automated backups deployed from the examples or managed provider and a recorded staging restore drill
- queue worker supervision runbook exists; staging/production supervision is not proven yet
- scheduler supervision runbook exists; staging/production supervision is not proven yet
- production logging without sensitive data leakage
- monitoring/alerting runbook exists, but no production integration is proven yet
- error tracking provider and PII redaction must be selected and verified
- alerting for failed jobs and payment/billing failures
- database connection least privilege

## Security Review Checklist

Before shipping a sensitive feature:

1. Is input validated?
2. Is authorization explicit?
3. Is tenant scope guaranteed?
4. Is money calculated only server-side?
5. Are dangerous state changes audited?
6. Are rate limits and idempotency needed?
7. Are sensitive fields hidden from unauthorized users?
8. Are tests covering denied access?
9. Are secrets kept out of code?
10. Is the living roadmap updated if this changes the security posture?
