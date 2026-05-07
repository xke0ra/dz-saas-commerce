# Testing Strategy

Last updated: 2026-05-07

This document defines how the project should be tested as it grows into a commercial SaaS platform.

## Current Test Stack

Backend:

- Pest 4
- PHPUnit 12
- Laravel testing helpers
- PostgreSQL testing database: `dz_saas_commerce_testing`
- Scout collection driver in tests
- Queue sync driver in tests

Frontend:

- TypeScript compiler
- Next.js build
- Playwright e2e tests

## Current Baseline

Latest recorded verification:

- Backend: `150 passed (610 assertions)`
- Storefront build: passed
- Storefront typecheck: passed
- Storefront Playwright e2e: not currently verified in the 2026-05-06/2026-05-07 WSL environment. The test suite exists, but Chromium failed before executing scenarios because `libnspr4.so` was missing, and the configured `pnpm` command was not available in PATH.
- CI baseline: `.github/workflows/quality.yml` exists, but has not yet been proven as an active GitHub Actions merge gate because the workspace root is not currently a Git repository.

These numbers must be updated in the living roadmap when they change.

## CI Baseline

The current CI contract is defined in `.github/workflows/quality.yml`.

Jobs:

- backend: PostgreSQL service, `composer validate`, `composer install`, `.env.testing.example`, `migrate:fresh --seed`, `php artisan system:health --scope=ready --format=json`, `php artisan test`, `php artisan route:list`
- storefront: `pnpm install --frozen-lockfile`, `pnpm typecheck`, `pnpm build`
- docker-check: `docker buildx build --check` for backend and storefront Dockerfiles
- e2e: optional behind `RUN_E2E=true`, installs Playwright Chromium and uploads artifacts on failure

This workflow must become a required pull request gate before large Codex-driven feature work. Until then, local verification remains mandatory.

## Backend Test Command

```bash
cd backend
php artisan test
```

Run route verification when routes change:

```bash
cd backend
php artisan route:list
```

Run operational health verification after production-readiness changes:

```bash
cd backend
php artisan system:health --scope=live --format=json
php artisan system:health --scope=ready --format=json
php artisan test tests/Feature/System/SystemHealthTest.php
```

Run scheduled maintenance checks when scheduler commands change:

```bash
cd backend
php artisan schedule:list
php artisan test tests/Feature/Checkout/CheckoutIdempotencyPruneTest.php
```

Run browser/security header smoke tests when headers or middleware change:

```bash
cd backend
php artisan test tests/Feature/Security/SecurityHeadersTest.php
```

Run migrations when database structure changes:

```bash
cd backend
php artisan migrate
php artisan test
```

## Frontend Test Commands

```bash
cd storefront
corepack enable
corepack prepare pnpm@10.33.2 --activate
pnpm typecheck
pnpm build
pnpm test:e2e
```

Do not run `pnpm typecheck` while `pnpm build` is regenerating `.next/types`.

Temporary fallback when `pnpm` is unavailable:

```bash
cd storefront
npm run typecheck
npm run build
npx --yes pnpm@10.33.2 exec playwright test
```

## What Must Be Tested

P0 areas:

- tenant isolation
- authorization and policies
- checkout totals
- checkout idempotency and duplicate-window behavior
- checkout idempotency pruning when retention behavior changes
- checkout abuse limits by IP/phone/store when changed
- inventory reservation and release
- order status transitions
- payment status transitions
- subscription limits and suspension behavior
- shipping calculation
- domain resolution
- public storefront availability rules
- support access boundaries
- system liveness/readiness endpoints and `system:health` command
- production runtime safeguards for `APP_DEBUG` and `APP_KEY`
- security headers and CSP/HSTS smoke behavior when changed
- trusted proxy behavior for `X-Forwarded-Proto` / HSTS behind reverse proxies

P1 areas:

- Filament vendor workflows
- billing payment confirmation and rejection
- coupons
- returns
- shipment lifecycle
- analytics widgets
- staff invitations
- audit logs

P2 areas:

- visual regressions
- SEO metadata
- theme rendering
- mobile usability
- performance budgets

## Tenant Isolation Tests

Every tenant-owned domain must include tests proving:

1. records from current tenant are visible
2. records from another tenant are hidden
3. creation assigns the correct tenant
4. cross-tenant references are rejected
5. super admin behavior is explicitly tested when relevant

Use `App\Support\Tenancy\CurrentTenant` in tests to set context where needed.

## Checkout Tests

Checkout tests must prove that Laravel, not the frontend, calculates:

- product price
- subtotal
- discount
- shipping fee
- total
- payment status
- inventory validity

Tests should include:

- valid quick order
- unavailable product
- invalid wilaya/commune combination
- missing shipping rate
- inactive COD payment method
- insufficient inventory
- cross-tenant product rejection
- coupon constraints
- idempotency key replay without creating a second order
- idempotency conflict for the same key with a different payload
- tenant/store isolation for idempotency records
- duplicate checkout window when the header is absent

## Policy Tests

Policy tests should cover:

- tenant owner
- store admin
- store staff
- platform support
- super admin
- user with permission overrides
- user without membership

Policy tests should assert both allow and deny paths.

## Filament Tests

Filament workflows need tests when they contain business-impacting behavior:

- list scoping
- create tenant assignment
- edit authorization
- custom table actions
- status transition actions
- payment confirmation/rejection
- support ticket assignment/status changes

Filament tests must not replace domain action tests. Actions remain the primary behavior unit.

## E2E Tests

Current Playwright tests are storefront-focused. As the storefront grows, e2e coverage should include:

- homepage loads for a resolved store
- product listing
- product details
- quick order happy path
- cart checkout happy path with item payloads
- sitemap, robots, canonical, and OpenGraph smoke coverage
- structured data JSON-LD smoke coverage
- mobile navigation smoke coverage
- storefront trust and contact section smoke coverage
- mobile cart checkout CTA coverage
- track order
- store unavailable page
- Arabic/RTL smoke test
- mobile viewport checkout

Later dashboard e2e tests may be added for high-value merchant workflows.

Current distinction:

- Mocked storefront e2e specs exist in `storefront/tests/e2e`.
- Real integration e2e against a live Laravel backend is not yet established.
- Playwright browser system dependencies must be part of local setup and CI before e2e can be treated as a required quality gate.

## Test Data

Factories exist for many core models. New model factories should:

- set valid defaults
- keep tenant consistency
- avoid hidden cross-tenant relations
- support overrides for negative tests

Seeders are for local/dev data, not test assertions unless a test explicitly seeds a known dataset.

## Performance Testing Direction

Before production launch, add repeatable checks for:

- slow query detection in critical endpoints
- storefront Lighthouse baseline
- checkout p95 under expected local load
- queue latency for billing and notifications
- Meilisearch indexing health

These do not need to block every development step yet, but they must be part of production readiness.

## Coverage Rule

Do not chase coverage percentage alone. The required standard is risk-based coverage:

- high-risk money, tenant, order, inventory, payment, subscription logic must have tests
- low-risk display-only changes may be verified by typecheck/build/e2e smoke tests
- every bug fix should add a regression test when practical
