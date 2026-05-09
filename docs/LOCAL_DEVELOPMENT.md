# Local Development Setup

Last updated: 2026-05-09

This is the clean-clone local setup contract for `dz-saas-commerce`. It intentionally uses local-only dummy credentials from `docker-compose.yml`; rotate anything used outside local development.

## Repository Shape

Current observed shape:

- Workspace root: `dz-saas-commerce/`
- Git repository root: `dz-saas-commerce/.git`
- Backend, storefront, docs, deploy examples, and Docker Compose live in the same root repository.

Repository strategy:

- Current strategy is a root monorepo.
- Do not reintroduce nested Git repositories without an ADR explaining ownership, release coordination, and CI impact.

## Prerequisites

- PHP 8.3+
- Composer
- Node.js compatible with Next.js 15
- pnpm 10.33.2
- Docker and Docker Compose
- PostgreSQL client tools are useful for debugging

If `pnpm` is not available, enable it with Corepack:

```bash
corepack enable
corepack prepare pnpm@10.33.2 --activate
```

Temporary fallback:

```bash
npx --yes pnpm@10.33.2 --version
```

## Local Services

From the workspace root:

```bash
docker compose up -d postgres redis meilisearch minio mailpit
```

Services:

- PostgreSQL: `127.0.0.1:5432`
- Redis: `127.0.0.1:6379`
- Meilisearch: `127.0.0.1:7700`
- MinIO API: `127.0.0.1:9000`
- MinIO console: `127.0.0.1:9001`
- Mailpit SMTP: `127.0.0.1:1025`
- Mailpit UI: `127.0.0.1:8025`

The local credentials in `docker-compose.yml` and `.env.example` are dummy development values only.

## Backend Setup

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Useful seeders:

```bash
php artisan db:seed --class=PlanSeeder
php artisan db:seed --class=AlgeriaGeographySeeder
php artisan db:seed --class=StorefrontDemoSeeder
```

## Backend Workers

Queue worker:

```bash
cd backend
php artisan queue:work --tries=3
```

Scheduler loop for local development:

```bash
cd backend
php artisan schedule:work
```

For a one-shot scheduler smoke check:

```bash
cd backend
php artisan schedule:run
```

## Storefront Setup

Preferred clean-clone verification path when Node/pnpm are not installed in WSL:

```bash
./storefront/scripts/verify-docker.sh all
```

This uses Docker with:

- `node:24-bookworm` for install, typecheck, and build
- `mcr.microsoft.com/playwright:v1.59.1-noble` for e2e
- `pnpm@10.33.2` through Corepack

The script mounts only `storefront/`, keeps pnpm's store inside the container under `/tmp/pnpm-store`, and avoids mixing Windows `node_modules` with WSL `node_modules`. The first Playwright run may take several minutes while Docker pulls the browser image.

Native WSL setup is still supported if Node and pnpm are installed inside WSL:

```bash
cd storefront
cp .env.example .env.local
pnpm install
pnpm dev --hostname 127.0.0.1 --port 3000
```

Set `NEXT_PUBLIC_DEFAULT_STORE` or `DEFAULT_STORE_IDENTIFIER` in `storefront/.env.local` to a seeded store slug/subdomain/domain/id when developing on `127.0.0.1`.

Use one runtime environment consistently for the storefront. If the workspace checkout lives inside WSL, install and run Node/pnpm inside WSL or use Docker. Do not run Windows Node against a WSL pnpm `node_modules` tree through `\\wsl.localhost`; pnpm symlinks can produce misleading module-resolution failures that are not reliable code errors.

## Verification Commands

Backend:

```bash
cd backend
php artisan test
php artisan route:list
php artisan migrate:status
```

Storefront:

```bash
cd storefront
pnpm typecheck
pnpm build
```

Playwright e2e:

```bash
cd storefront
pnpm test:e2e
```

If Chromium fails to launch with missing shared libraries in native WSL, install Playwright system dependencies for the host OS. The Docker verification path avoids this by using the official Playwright image.

As of the 2026-05-09 verification pass, the Docker path successfully ran `pnpm install --frozen-lockfile`, `pnpm typecheck`, `pnpm build`, and `pnpm test:e2e`. The run used Node `v24.15.0`, pnpm `10.33.2`, and Playwright reported `6 passed`.

Repository hygiene and clean export checks:

```bash
scripts/security/secret-hygiene.sh
scripts/release/clean-export-check.sh
```

## Clean Workspace Rules

Do not commit or package:

- `.env`
- `.env.local`
- `vendor/`
- `node_modules/`
- `.next/`
- `storage/logs/`
- local SQLite files
- coverage output
- Playwright `test-results/` or `playwright-report/`

Commit examples and docs:

- `.env.example`
- `.env.testing.example`
- `.env.production.example` when added
- `docs/*.md`

## Secret Handling

- Treat any value from `.env`, `.env.local`, old ZIP files, chat logs, or screenshots as exposed if it was shared.
- Rotate exposed values before using any environment beyond local development.
- Production secrets must come from environment variables or a secret manager, not committed files.
