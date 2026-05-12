# Development Workflow

Last updated: 2026-05-12

This document defines the working process for this project. It is optimized for careful, incremental development by a human or AI agent.

## Core Rule

Never make broad uncontrolled rewrites. Inspect first, change narrowly, verify, then update the living roadmap when the project state changes.

The living roadmap is:

- `docs/PROJECT_DEEP_ANALYSIS_AND_AI_ROADMAP_AR.md`

Architecture decisions live in:

- `docs/adr/`

Do not change an accepted architecture decision without adding or updating an ADR.

## Before Any Change

Inspect the relevant files:

```bash
rg -n "ClassOrConcept" backend/app backend/database backend/routes backend/tests
rg -n "ComponentOrConcept" storefront/src storefront/tests
```

Check worktree state:

```bash
git status --short
```

Note: the repository root is the Git repository. Do not revert unrelated changes.

## Local Setup

Use `docs/LOCAL_DEVELOPMENT.md` as the local setup contract. It documents:

- Docker Compose services
- backend install and seed commands
- storefront install commands
- queue worker and scheduler commands
- verification commands
- generated artifacts that must not be committed or packaged

The workspace root is the repository boundary. Check the full root worktree before making changes.

## Production Build Artifacts

Production foundation artifacts are documented in `docs/PRODUCTION_READINESS.md`.

Current image build commands:

```bash
docker build -f backend/Dockerfile -t dz-saas-commerce-backend:local backend
docker build -f storefront/Dockerfile -t dz-saas-commerce-storefront:local storefront
```

The first staging compose skeleton is in `deploy/staging/`. It expects immutable GHCR image tags or digests and staging-only env files copied from the committed examples.

For a runner-local proof before real staging services are ready, use `deploy/staging/staging-ephemeral-smoke.sh`. It overlays disposable PostgreSQL, Redis, Meilisearch, MinIO, and Mailpit services, generates temporary staging env files, runs migrations and `StorefrontDemoSeeder`, then executes the same readiness and edge checks.

Do not add real production secrets to image builds. Runtime configuration must come from environment variables or a secret manager.

## CI Quality Gates

The baseline workflow is:

- `.github/workflows/quality.yml`
- `.github/workflows/container-images.yml`
- `.github/workflows/staging-smoke.yml`

Current status:

- The workflow defines repository hygiene, backend, storefront, Docker image check/build/scan, and storefront e2e jobs.
- Repository hygiene blocks tracked local env files, private keys, generated artifacts, and high-confidence secret patterns.
- Backend CI includes Composer audit and Pint.
- Storefront CI includes pnpm audit at `moderate` or higher.
- Dockerfile Checks builds backend/storefront images and runs Trivy image vulnerability scans for fixed `HIGH` and `CRITICAL` OS/library vulnerabilities.
- Container image publishing builds, scans, and pushes backend/storefront images to GHCR on manual dispatch or version tags.
- Staging smoke is a manual workflow with `target=environment` for real staging env files and `target=ephemeral` for disposable runner-local backing services.
- The workflow was proven green in GitHub Actions on PR #1 / run `25743248405`.
- As of 2026-05-12, main branch protection requires `Repository Hygiene`, `Backend`, `Storefront`, `Dockerfile Checks`, and `Storefront E2E` with strict status checks and admin enforcement enabled.
- Treat this as the active CI contract for pull requests into `main`.

Required CI gates before broad AI/Codex development:

- `Repository Hygiene`
- `Backend`
- `Storefront`
- `Dockerfile Checks`
- `Storefront E2E`

Do not mark CI as complete after future workflow edits until a real GitHub Actions run proves these jobs again on the repository that receives pull requests.

Do not mark real staging as proven until **Staging Smoke** has run with `target=environment` and `mode=all` against real staging backing services and the result is recorded in `docs/PRODUCTION_READINESS.md`. A successful `target=ephemeral` run proves image/process compatibility only.

Run repository hygiene locally when env, deployment, CI, ignore rules, or release packaging changes:

```bash
scripts/security/secret-hygiene.sh
scripts/security/container-image-scan.sh dz-saas-commerce-backend:ci
scripts/release/clean-export-check.sh
```

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
./storefront/scripts/verify-docker.sh all
```

This is the preferred clean-clone path when the checkout is inside WSL but Node/pnpm are not installed in WSL. It uses Docker, Node 24, pnpm 10.33.2, and the official Playwright image.

Native WSL/Linux commands are also acceptable when Node and pnpm are installed in the same environment as the checkout:

```bash
cd storefront
pnpm install --frozen-lockfile
pnpm audit --audit-level moderate
pnpm build
pnpm typecheck
pnpm test:e2e
```

Do not run `pnpm typecheck` concurrently with `pnpm build`; `.next/types` can be regenerated during build. A 2026-05-12 parallel local run reproduced this failure mode, while sequential `build` then `typecheck` passed.

If native Playwright fails before executing tests because the browser cannot launch, either fix host system dependencies or use `./storefront/scripts/verify-docker.sh e2e`. The full Docker storefront path passed on 2026-05-12 with Playwright reporting `6 passed`.

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
pnpm audit --audit-level moderate
pnpm build
pnpm typecheck
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
pnpm audit --audit-level moderate
pnpm build
pnpm typecheck
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

Run the automated hygiene check before packaging or when touching env, deployment, CI, or ignore rules:

```bash
scripts/security/secret-hygiene.sh
scripts/release/clean-export-check.sh
```

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
