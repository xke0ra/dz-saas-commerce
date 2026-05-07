# Development Workflow

Last updated: 2026-05-07

This document defines the working process for this project. It is optimized for careful, incremental development by a human or AI agent.

## Core Rule

Never make broad uncontrolled rewrites. Inspect first, change narrowly, verify, then update the living roadmap when the project state changes.

The living roadmap is:

- `docs/PROJECT_DEEP_ANALYSIS_AND_AI_ROADMAP_AR.md`

## Before Any Change

Inspect the relevant files:

```bash
rg -n "ClassOrConcept" backend/app backend/database backend/routes backend/tests
rg -n "ComponentOrConcept" storefront/src storefront/tests
```

Check worktree state:

```bash
git -C backend status --short
```

Note: the repository root is not currently a git repository. `backend/` is a git repository. Do not revert unrelated changes.

## Local Setup

Use `docs/LOCAL_DEVELOPMENT.md` as the local setup contract. It documents:

- Docker Compose services
- backend install and seed commands
- storefront install commands
- queue worker and scheduler commands
- verification commands
- generated artifacts that must not be committed or packaged

The workspace root currently is not a Git repository. Until the repository strategy is normalized, check both the root-level hygiene files and the `backend/` Git worktree before making changes.

## Production Build Artifacts

Production foundation artifacts are documented in `docs/PRODUCTION_READINESS.md`.

Current image build commands:

```bash
docker build -f backend/Dockerfile -t dz-saas-commerce-backend:local backend
docker build -f storefront/Dockerfile -t dz-saas-commerce-storefront:local storefront
```

Do not add real production secrets to image builds. Runtime configuration must come from environment variables or a secret manager.

## CI Quality Gates

The baseline workflow is:

- `.github/workflows/quality.yml`

Current status:

- The workflow defines backend, storefront, Dockerfile check, and optional storefront e2e jobs.
- The workspace root is not currently a Git repository, so this workflow is not yet proven as an active merge gate.
- Treat it as the target CI contract until repository strategy is normalized and the workflow runs inside GitHub Actions.

Required CI gates before broad AI/Codex development:

- backend readiness smoke passes
- backend tests pass
- storefront typecheck/build pass
- Dockerfile checks pass
- e2e artifacts upload on failure when e2e is enabled

Do not mark CI as complete until a real GitHub Actions run proves these jobs on the repository that receives pull requests.

## Health And Readiness

Backend operational checks:

```bash
cd backend
php artisan system:health --scope=live --format=json
php artisan system:health --scope=ready --format=json
```

Public endpoints:

- `GET /api/system/health/live`
- `GET /api/system/health/ready`

Use liveness for process/container boot checks. Use readiness for dependency checks before routing traffic.

## Package Manager Rule

The storefront declares `pnpm@10.33.2` in `storefront/package.json`.

Before running storefront commands, make `pnpm` available:

```bash
corepack enable
corepack prepare pnpm@10.33.2 --activate
```

Temporary fallback when Corepack is unavailable:

```bash
npx --yes pnpm@10.33.2 --version
```

## Backend Changes

For Laravel changes:

1. Read the model/action/policy/resource/test files around the target behavior.
2. Put business logic in `app/Actions` or `app/Support`.
3. Use Form Requests or clear validation for API input.
4. Use policies for dashboard authorization.
5. Keep Filament resources declarative where possible.
6. Add or update tests for business rules.

Run after backend behavior changes:

```bash
cd backend
php artisan test
php artisan route:list
```

Run after migrations:

```bash
cd backend
php artisan migrate
php artisan test
```

Run formatting when PHP files were changed:

```bash
cd backend
./vendor/bin/pint
```

## Frontend Changes

For Next.js changes:

1. Preserve server-side tenant resolution in `storefront/src/lib/store-context.ts`.
2. Keep trusted calculations in Laravel.
3. Prefer typed data flow through `storefront/src/lib/types.ts`.
4. Keep UI responsive and Arabic/French/RTL-ready.
5. Avoid adding client-side JavaScript unless interaction requires it.

Run after storefront changes:

```bash
cd storefront
pnpm typecheck
pnpm build
pnpm test:e2e
```

Do not run `pnpm typecheck` concurrently with `pnpm build`; `.next/types` can be regenerated during build.

If Playwright fails before executing tests because the browser cannot launch, fix host system dependencies first. The current verified blocker on 2026-05-06 was a missing `libnspr4.so` for Chromium in WSL.

## Route Changes

After changing Laravel routes:

```bash
cd backend
php artisan route:list
php artisan test
```

After changing Next.js route files:

```bash
cd storefront
pnpm typecheck
pnpm build
```

## Checkout Changes

Checkout is money and inventory sensitive.

Any checkout change must preserve:

- Laravel-side totals
- tenant/store scoping
- inventory locking/reservation behavior
- `Idempotency-Key` replay behavior
- conflict behavior for the same key with a different payload
- duplicate-window behavior when the key is absent
- rate limits by IP/phone/store when abuse controls are touched

Required verification for checkout changes:

```bash
cd backend
php artisan test tests/Feature/Checkout/QuickCheckoutTest.php
php artisan test

cd ../storefront
npm run typecheck
npm run build
```

## Package Changes

Do not add packages unless the local codebase clearly benefits and compatibility is checked.

For Laravel packages, verify compatibility with:

- Laravel 13
- PHP 8.3+
- Filament 5.6+ when dashboard-related

For frontend packages, verify compatibility with:

- Next.js 15
- React 19
- TypeScript 5.7+

After package changes:

```bash
cd backend
composer install
composer test

cd ../storefront
pnpm install
pnpm build
```

## Database Changes

New tenant-owned migrations must include:

- `tenant_id`
- foreign key to `tenants`
- tenant-scoped indexes
- composite uniqueness for tenant-specific slugs or numbers
- same-tenant composite foreign keys where practical

Use PostgreSQL constraints for correctness. Do not rely only on application validation for cross-tenant integrity.

## Filament 5 Rules

This project uses Filament 5. Inspect existing generated files before adding resources.

Current patterns:

- providers extend `Filament\PanelProvider`
- resources use `Filament\Resources\Resource`
- forms use `Filament\Schemas\Schema`
- resource forms/tables are split into `Schemas` and `Tables` classes
- panel discovery is configured in provider classes

Do not use outdated Filament 3 syntax without checking compatibility.

## Documentation Update Rule

Update `docs/PROJECT_DEEP_ANALYSIS_AND_AI_ROADMAP_AR.md` after a change when any of these are true:

- milestone status changed
- next priority changed
- a new risk was discovered
- a risk was closed
- a domain rule changed
- verification results changed
- a new mandatory workflow rule was introduced

Update the relevant domain doc too:

- architecture: `docs/ARCHITECTURE.md`
- tenancy: `docs/TENANCY_RULES.md`
- tests: `docs/TESTING_STRATEGY.md`
- security: `docs/SECURITY_BASELINE.md`
- local setup: `docs/LOCAL_DEVELOPMENT.md`
- production setup: `docs/PRODUCTION_READINESS.md`

## Repository Hygiene Rule

Do not commit or package local/generated files:

- `.env`
- `.env.local`
- `vendor/`
- `node_modules/`
- `.next/`
- `storage/logs/`
- local SQLite files
- coverage output
- Playwright `test-results/` or `playwright-report/`

Keep examples committed:

- `.env.example`
- `.env.testing.example`
- `.env.production.example` when added

## Commit-Ready Checklist

A change is commit-ready when:

1. Relevant code was inspected first.
2. The change is scoped to the requested behavior.
3. Business logic is not inside controllers or Filament resources.
4. Tenant isolation was considered.
5. Tests were added or the lack of tests is justified.
6. Verification commands were run.
7. Living roadmap was updated if the state changed.
8. No unrelated files were reverted.
9. CI gate impact was considered when adding commands, services, or packages.
