# Local Development Setup

Last updated: 2026-05-07

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

```bash
cd storefront
cp .env.example .env.local
pnpm install
pnpm dev --hostname 127.0.0.1 --port 3000
```

Set `NEXT_PUBLIC_DEFAULT_STORE` or `DEFAULT_STORE_IDENTIFIER` in `storefront/.env.local` to a seeded store slug/subdomain/domain/id when developing on `127.0.0.1`.

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

If Chromium fails to launch with missing shared libraries, install Playwright system dependencies for the host OS. In the current WSL environment, `libnspr4.so` was missing during verification on 2026-05-06.

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
