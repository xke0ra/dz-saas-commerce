# Environment Requirements (values redacted; shapes only)

**Audit:** 2026-10-01. Sources: `backend/.env.example`, `.env.production.example`, `.env.testing.example`, `storefront/.env.example`, `.env.production.example`, `deploy/staging/*.env.example`, `deploy/backup/backup.env.example`, `staging-smoke.yml:71-94`.

## Development runtimes

| Component | Required | Evidence |
|---|---|---|
| OS | Any Linux (or WSL2); Windows-native Node + WSL `node_modules` mixing unsupported | `TESTING_STRATEGY.md:185` |
| PHP / Composer | PHP 8.3, Composer 2 (host verified 8.3.6 / 2.9.7) | `backend/composer.json:9`, Dockerfiles, `quality.yml:55` |
| Node / pnpm | Node 24, pnpm 11.1.2 (enforced `packageManager`) | `storefront/package.json:5`, Dockerfiles, `quality.yml` |
| Docker + compose | For postgres/redis/meili/minio/mailpit (or provide equivalents) | `docker-compose.yml` |
| Services | Postgres 17, Redis 7, Meilisearch v1.12, MinIO (S3), Mailpit | compose + `backend/.env.example` |

## Backend env vars (`backend/.env.example`, 75 lines)

Required: `APP_NAME/ENV/KEY/DEBUG/URL`, `TRUSTED_PROXIES`, locale (`APP_LOCALE=ar`, fallback `fr`), `BCRYPT_ROUNDS`, log (`LOG_CHANNEL/STACK/LEVEL`), `DB_CONNECTION/HOST/PORT/DATABASE/USERNAME/PASSWORD`, `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER`, `MAIL_*` (Mailpit 1025), `FILESYSTEM_DISK` + `PRODUCT_IMAGES_DISK`, S3 (`AWS_*`, MinIO endpoint), Scout/Meili (`SCOUT_DRIVER`, `MEILISEARCH_HOST/KEY`), PULSE/TELESCOPE/NIGHTWATCH toggles.
Production-only additions: `APP_DEBUG=false`, `LOG_STACK=stderr`, redis-backed cache/queue/session, `FILESYSTEM_DISK=s3`, `DB_SSLMODE=require`, `SESSION_ENCRYPT/SECURE_COOKIE`, `SCOUT_PREFIX`, `ASSET_URL/SESSION_DOMAIN`, `SESSION_DOMAIN`. Testing overrides come from `phpunit.xml` (array drivers, sync queue, collection Scout, `dz_saas_commerce_testing` DB).

## Storefront env vars

`NEXT_PUBLIC_API_BASE_URL`, `NEXT_PUBLIC_ASSET_BASE_URL`, `NEXT_PUBLIC_DEFAULT_STORE` / `DEFAULT_STORE_IDENTIFIER`, `NEXT_PUBLIC_STOREFRONT_BASE_URL` / `STOREFRONT_BASE_URL` (dev defaults `127.0.0.1:3101/3100`, `demo-store` in `package.json:11` + `.env.example`). Prod: `https://{api,assets,storefront}.example.com` placeholders. `NEXT_PUBLIC_*` baked at build → rebuild per environment.

## Staging contract (`staging-smoke.yml`)

7 secrets (`STAGING_APP_KEY/DB_PASSWORD/MEILI_MASTER_KEY/REDIS_PASSWORD/MAIL_PASSWORD/AWS_*`) + 15 vars (URLs/hosts/buckets) via GitHub `staging` environment; rendered to ignored `images.env/backend.env/storefront.env`. No real values in repo (CI secret-hygiene enforced; none found).

## Secret handling rules (per `SECURITY_BASELINE.md:181-211`)

Never commit `.env`/`.env.local`; examples carry placeholders only; prod secrets from env/manager; rotate on migration; staging via environment contract. Backup checklist for reinstall: `backend/.env`, `storefront/.env.local`, `deploy/staging/*.env`.
