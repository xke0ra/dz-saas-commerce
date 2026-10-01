# Stage 10: Docker / Local Reproducibility Gate

**Audit Date:** 2026-10-01
**Audit Branch:** `main`
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`

## 1. Files inspected

`docker-compose.yml`, `backend/Dockerfile`, `storefront/Dockerfile`, `.devcontainer/{Dockerfile,docker-compose.yml,devcontainer.json}`, `deploy/staging/docker-compose.staging.example.yml`, `docker-compose.staging.ephemeral.yml`, `backend/.env.example`, `backend/.env.production.example`, `backend/.env.testing.example`, `storefront/.env.example`, `storefront/.env.production.example`, `deploy/staging/*.env.example`, `scripts/*`.

## 2. Local stack (`docker-compose.yml:1-59`)

Postgres 17-alpine (volume `postgres_data`), Redis 7-alpine (NO volume, ephemeral), Meilisearch v1.12 (volume `meili_data`), MinIO latest (volumes, ports 9000/9001), Mailpit latest (no volume). No healthchecks, no env_file, hardcoded dummy creds (correct for local dev). Verified running in this audit host: `dz_postgres`, `dz_redis`, `dz_mailpit`, `dz_meilisearch` all Up.

## 3. Images

- Backend (96 lines, 3 stages): `composer:2` vendor → `node:24-alpine` assets → `php:8.3-fpm-alpine` runtime; ext bcmath/intl/opcache/pcntl/pdo_pgsql/zip + `pecl redis-6.3.0`; `USER www-data` (non-root); `HEALTHCHECK php artisan system:health --scope=live`. Strong.
- Storefront (54 lines, 4 stages): `node:24-alpine` throughout; `pnpm install --frozen-lockfile`; strips package managers in runner; `CMD next start`. Gaps: runs as root (no USER), no HEALTHCHECK; `NEXT_PUBLIC_*` baked at build time (rebuild per env — documented constraint).
- Devcontainer: `mcr.microsoft.com/devcontainers/php:1-8.3-bookworm` + Node 24 + pnpm 11.1.2, ends `USER vscode`; `files.eol \n`.

## 4. Local vs staging vs prod env

- Local: `APP_DEBUG=true`, `LOG_STACK=single`, `FILESYSTEM_DISK=local`, DB `127.0.0.1` (`backend/.env.example`).
- Staging: `APP_ENV=staging, APP_DEBUG=false, LOG_STACK=stderr, CACHE/QUEUE/SESSION=redis, FILESYSTEM_DISK=s3, DB_SSLMODE=require, SESSION_SECURE_COOKIE=true`, edge bound `127.0.0.1:8080`.
- Prod: same shape + `SCOUT_PREFIX=production_`, empty hosts/secrets. No real secrets in examples (verified by secret-hygiene CI job).

## 5. Findings

- OPS-001 (LOW, CONFIRMED): storefront runner is root + no HEALTHCHECK; backend has both. Fix: add `USER node`/`nodejs` + `HEALTHCHECK CMD wget -qO- http://127.0.0.1:3000/`.
- OPS-002 (LOW, CONFIRMED): floating tags `minio/minio:latest`, `minio/mc:latest`, `axllent/mailpit:latest`, `composer:2`; `node:24-alpine` (Dockerfiles) vs `node:24-bookworm` in `scripts/ops/verify-docker.sh:7` + `LOCAL_DEVELOPMENT.md:116`. Pin digests for supply-chain stability.
- OPS-003 (INFO): root compose redis/mailpit ephemeral — acceptable for dev (documented), devcontainer redis HAS a volume (inconsistency, harmless).
- Tailwind major split: backend Vite app uses tailwind 4, storefront uses tailwind 3.4.17 — separate apps, no breakage, noted for design awareness.

## 6. Reproducibility gate verdict

PASS WITH FINDINGS. A fresh Linux machine with Docker + PHP 8.3/Composer + Node 24/pnpm 11 can reproduce dev via `docker compose up` + env examples. Verified live: containers start, backend boots (`route:list` OK), test DB reachable, Pint/tsc green. Findings are hardening, not blockers.
