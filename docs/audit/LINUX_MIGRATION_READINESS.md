# Linux Migration Readiness

**Audit:** 2026-10-01, `main` @ `bd58620`. Gate report: `stage-17-linux-migration-gate.md`.

## Verdict: SAFE WITH REQUIRED PRE-MIGRATION ACTIONS

No blockers. The repo is clone-complete; local state is disposable or reproducible. Do the checklist, then reinstall freely.

## Pre-migration checklist (do all before wiping)

1. **Push the ahead commit.** Local `main` is 1 ahead of `origin/main` (`bd58620` Zone.Identifier cleanup). `git push origin main`; verify `git rev-list --left-right --count HEAD...@{u}` = `0 0`.
2. **Commit or copy this audit.** `docs/audit/` is untracked (9 pre-existing stage files + this audit's 17 files). Commit to `main` or copy the folder to safe storage.
3. **Back up ignored secrets (NOT on GitHub):** `backend/.env`, `storefront/.env.local`, any `deploy/staging/*.env`, `deploy/backup/*.env`. Copy to encrypted offline storage. Values needed on Linux: APP_KEY, DB/Redis/Meili/MinIO passwords + keys, mail credentials, S3 config. Env *shapes* are in `*.example` files (complete) — only *values* need backup.
4. **Decide on dev-DB data.** `dz_saas_commerce` dev DB is disposable (`migrate:fresh --seed` rebuilds; CI proves it). If you hand-created rows outside seeders, `pg_dump` them now or lose them. Testing DB needs no backup (rebuilt per run).
5. **Note local-only artifacts to discard:** `backend/vendor/`, `storefront/node_modules/`, `storefront/.next/`, `backend/bootstrap/cache/*.php` — all regenerable; do NOT copy (esp. `node_modules` across OSes).

## Fresh Linux setup

- Install: Docker Engine 24+ (compose plugin), PHP 8.3 + extensions (bcmath, intl, pdo_pgsql, redis, zip), Composer 2, Node 24, pnpm 11.1.2 (`corepack` or standalone; `packageManager` field enforces it), Postgres client (for `pg_dump` parity, optional).
- Clone, then: `docker compose up -d` (postgres/redis/meili/minio/mailpit); `cp backend/.env.example backend/.env` (+ restore secrets, `php artisan key:generate` if new); `cp storefront/.env.example storefront/.env.local`; `composer install`; `php artisan migrate --seed`; `(cd storefront && pnpm install)`.
- Verify (all proven green on 2026-10-01): `php vendor/bin/pint --test` (574 files), `(storefront) tsc --noEmit`, `php artisan route:list`, `php artisan test tests/Feature/Tenancy/TenantFoundationTest.php`, `.../Checkout/QuickCheckoutTest.php`.

## Known Linux-side notes

Scripts are portable (`env bash`, LF via `.gitattributes`, no `.bat`); service DNS inside compose vs `127.0.0.1` on host is already correct per file (devcontainer uses service names; local env uses 127.0.0.1). Host Node here was v20 while images/CI use Node 24 — install Node 24. Global `pnpm` was missing on this host — install it. CRLF/exec-bit state UNVERIFIED by tooling; after clone, spot-check `git ls-files -s -- scripts backend/artisan` + `file` if anything misbehaves.
