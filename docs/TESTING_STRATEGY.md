# Testing Strategy

Last updated: 2026-05-12

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

Latest recorded verification: 2026-05-12.

- Backend: `154 passed (629 assertions)`.
- Repository hygiene: `scripts/security/secret-hygiene.sh` and `scripts/release/clean-export-check.sh` passed.
- Backend smoke checks passed: `composer audit --no-interaction`, `php vendor/bin/pint --test`, and `php artisan system:health --scope=ready --format=json`.
- Storefront build: passed.
- Storefront typecheck: passed when run sequentially after build.
- Storefront Playwright e2e: `6 passed`.
- Storefront dependency audit: passed. `pnpm audit --audit-level moderate` reports no known vulnerabilities after updating Next to `15.5.18`.
- Storefront Docker verification: `./storefront/scripts/verify-docker.sh all` passed on 2026-05-12 with Playwright reporting `6 passed`.
- Dockerfile checks and local image build smoke: backend and storefront passed on 2026-05-12 through `docker buildx build --check` and `docker buildx build --load`.
- CI baseline: `.github/workflows/quality.yml` now includes repository hygiene, clean export rehearsal, backend/frontend dependency audits, backend Pint, required storefront e2e, and Docker image build smoke checks, but has not yet been proven as an active required GitHub Actions merge gate. It should not be made a required merge gate until the workflow is proven green once on the real repository.

These numbers must be updated in the living roadmap when they change.

## CI Baseline

The current CI contract is defined in `.github/workflows/quality.yml`.

Jobs:

- repository-hygiene: runs `scripts/security/secret-hygiene.sh` and `scripts/release/clean-export-check.sh` to block tracked local env files, private keys, generated dependency/build artifacts, high-confidence leaked secret patterns, and dirty clean-export bundles
- backend: PostgreSQL service, `composer validate`, `composer install`, `composer audit`, `php vendor/bin/pint --test`, `.env.testing.example`, `migrate:fresh --seed`, `php artisan system:health --scope=ready --format=json`, `php artisan test`, `php artisan route:list`
- storefront: `pnpm install --frozen-lockfile`, `pnpm audit --audit-level moderate`, `pnpm typecheck`, `pnpm build`
- docker-check: `docker buildx build --check` and no-push image build smoke checks for backend and storefront Dockerfiles
- e2e: installs Playwright Chromium, runs `pnpm test:e2e`, and uploads artifacts on failure

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

Run queue/scheduler operating smoke checks when supervision docs or scheduled commands change:

```bash
cd backend
php artisan schedule:list
php artisan queue:failed
php artisan billing:process --sync
php artisan checkout-idempotency:prune --dry-run
```

Run monitoring/readiness smoke checks when monitoring or production operations docs change:

```bash
cd backend
php artisan system:health --scope=live --format=json
php artisan system:health --scope=ready --format=json
php artisan queue:failed
php artisan schedule:list
```

Run backup automation syntax checks when backup scripts or systemd examples change:

```bash
bash -n deploy/backup/bin/postgres-backup.sh.example
bash -n deploy/backup/bin/object-storage-sync.sh.example
bash -n deploy/backup/bin/staging-restore-drill.sh.example
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

Preferred Docker verification from the repository root:

```bash
./storefront/scripts/verify-docker.sh all
```

Individual Docker checks:

```bash
./storefront/scripts/verify-docker.sh install
./storefront/scripts/verify-docker.sh typecheck
./storefront/scripts/verify-docker.sh build
./storefront/scripts/verify-docker.sh e2e
```

Native WSL or Linux verification when Node/pnpm are installed in the same environment as the checkout:

```bash
cd storefront
corepack enable
corepack prepare pnpm@10.33.2 --activate
pnpm audit --audit-level moderate
pnpm build
pnpm typecheck
pnpm test:e2e
```

As of 2026-05-12 `pnpm audit --audit-level moderate` passes after updating Next to `15.5.18`.

Do not run `pnpm typecheck` while `pnpm build` is regenerating `.next/types`. A parallel local run on 2026-05-12 failed with missing generated `.next/types` files, while the sequential run passed.

Do not mix a Windows Node runtime with a WSL-created pnpm `node_modules` tree over `\\wsl.localhost` paths. For reliable storefront verification, run Node and pnpm in the same environment that owns the checkout, or use a Dockerized storefront verification job.

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
- scheduler registration and failed-jobs smoke checks when operating commands change
- repository secret hygiene when env, deployment, CI, or ignore rules change

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
- sitemap, robots, canonical, and OpenGraph smoke coverage, including multi-page product sitemap coverage
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

- Mocked storefront e2e specs exist in `storefront/tests/e2e` and pass through `./storefront/scripts/verify-docker.sh e2e`.
- Real integration e2e against a live Laravel backend is not yet established.
- Native Playwright browser system dependencies are still a local setup concern. The Docker path and CI job use the official Playwright/browser installation path and are the preferred quality-gate path.

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
