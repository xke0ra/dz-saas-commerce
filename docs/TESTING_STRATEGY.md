# Testing Strategy

Last updated: 2026-05-19

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

Latest full recorded verification remains 2026-05-12. The 2026-05-19 pass was documentation-only and did not rerun full backend/storefront suites because application code did not change.

- Backend historical full-suite baseline: `154 passed (629 assertions)`.
- Repository hygiene: `scripts/security/secret-hygiene.sh` and `scripts/release/clean-export-check.sh` passed.
- Backend smoke checks passed: `composer audit --no-interaction`, `php vendor/bin/pint --test`, and `php artisan system:health --scope=ready --format=json`.
- Storefront build: passed.
- Storefront typecheck: passed when run sequentially after build.
- Storefront Playwright e2e historical baseline: `6 passed` on 2026-05-12. The current spec now includes variant picker/simple-product/legacy-payload cases, but this docs-only pass did not rerun it.
- Storefront dependency audit: passed. `pnpm audit --audit-level moderate` reports no known vulnerabilities after updating Next to `15.5.18`.
- Storefront Docker verification: `./storefront/scripts/verify-docker.sh all` passed on 2026-05-12 with Playwright reporting `6 passed`.
- Dockerfile checks and local image build smoke: backend and storefront passed on 2026-05-12 through `docker buildx build --check` and `docker buildx build --load`.
- CI baseline: `.github/workflows/quality.yml` includes repository hygiene, clean export rehearsal, backend/frontend dependency audits, backend Pint, storefront e2e, Docker image build smoke checks, and image vulnerability scans. It passed in GitHub Actions on PR #1 / run `25743248405`, and main branch protection was documented as requiring all five Quality Gates checks.

These numbers must be updated in the living roadmap when they change.

## CI Baseline

The current CI contract is defined in `.github/workflows/quality.yml`.

Jobs:

- repository-hygiene: runs `scripts/security/secret-hygiene.sh` and `scripts/release/clean-export-check.sh` to block tracked local env files, private keys, generated dependency/build artifacts, high-confidence leaked secret patterns, and dirty clean-export bundles
- backend: PostgreSQL service, `composer validate`, `composer install`, `composer audit`, `php vendor/bin/pint --test`, `.env.testing.example`, `migrate:fresh --seed`, `php artisan system:health --scope=ready --format=json`, `php artisan test`, `php artisan route:list`
- storefront: `pnpm install --frozen-lockfile`, `pnpm audit --audit-level moderate`, `pnpm typecheck`, `pnpm build`
- docker-check: `docker buildx build --check` and no-push image build smoke checks for backend and storefront Dockerfiles
- e2e: installs Playwright Chromium, runs `pnpm test:e2e`, and uploads artifacts on failure

Treat this workflow as the required quality gate contract. If GitHub branch protection changes, update this document and `PRODUCTION_READINESS.md`; do not assume local verification alone is enough for production-facing work.

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

Run staging smoke checks when deployment scripts, Dockerfiles, runtime dependencies, or proxy config change:

```bash
BACKEND_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/backend:staging-YYYYMMDD-<sha> \
STOREFRONT_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-YYYYMMDD-<sha> \
deploy/staging/staging-ephemeral-smoke.sh all
```

For local validation of an unpublished backend image, tag it with a staging-style immutable tag and set `SKIP_PULL=1`. A successful ephemeral smoke proves process and dependency compatibility only; real staging still needs `target=environment` against externally managed services.

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
corepack prepare pnpm@11.1.2 --activate
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
- product variant checkout with `product_variant_id`
- simple-vs-variable `ProductType` enforcement
- checkout idempotency pruning when retention behavior changes
- checkout abuse limits by IP/phone/store when changed
- inventory reservation and release
- variant inventory uniqueness and lifecycle propagation
- stock movement ledger entries for reserve/release/settle/restock/manual adjustment
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
- store readiness validation for simple and variable products
- emergency 2FA reset behavior and audit metadata when security flows change

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
- product variant picker and variant checkout payloads
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
- `pnpm test:e2e` runs a production build first with the mock-backend storefront env, then Playwright starts the storefront with `pnpm exec next start` against the same mock backend. This avoids relying on Next.js dev cold compilation during CI/Docker e2e.
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
