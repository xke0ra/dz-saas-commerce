# GitHub Staging Environment Contract

The manual `.github/workflows/staging-smoke.yml` workflow can run either:

- `target=environment`: render ignored staging env files from the GitHub `staging` environment, then run `deploy/staging/staging-smoke.sh`.
- `target=ephemeral`: run `deploy/staging/staging-ephemeral-smoke.sh` with disposable PostgreSQL, Redis, Meilisearch, MinIO, and Mailpit services on the selected runner.

Use this workflow for repeatable validation once real staging backing services exist. It is not a production deployment workflow.

## Required Secrets

Configure these as environment secrets on `staging`:

- `STAGING_APP_KEY`
- `STAGING_DB_PASSWORD`
- `STAGING_MEILISEARCH_KEY`
- `STAGING_REDIS_PASSWORD`
- `STAGING_MAIL_PASSWORD`
- `STAGING_AWS_ACCESS_KEY_ID`
- `STAGING_AWS_SECRET_ACCESS_KEY`

## Required Variables

Configure these as environment variables on `staging`:

- `STAGING_APP_URL`
- `STAGING_DB_HOST`
- `STAGING_DB_DATABASE`
- `STAGING_DB_USERNAME`
- `STAGING_MEILISEARCH_HOST`
- `STAGING_REDIS_HOST`
- `STAGING_MAIL_HOST`
- `STAGING_MAIL_USERNAME`
- `STAGING_MAIL_FROM_ADDRESS`
- `STAGING_AWS_DEFAULT_REGION`
- `STAGING_AWS_BUCKET`
- `STAGING_NEXT_PUBLIC_API_BASE_URL`
- `STAGING_NEXT_PUBLIC_ASSET_BASE_URL`
- `STAGING_NEXT_PUBLIC_STOREFRONT_BASE_URL`
- `STAGING_STOREFRONT_BASE_URL`
- `STAGING_BACKEND_HOST`
- `STAGING_STOREFRONT_HOST`

## Optional Variables

These have workflow defaults when unset:

- `STAGING_EDGE_PORT`: defaults to `8080`
- `STAGING_TRUSTED_PROXIES`: defaults to `172.16.0.0/12`
- `STAGING_DB_PORT`: defaults to `5432`
- `STAGING_DB_SSLMODE`: defaults to `require`
- `STAGING_REDIS_PORT`: defaults to `6379`
- `STAGING_MAIL_SCHEME`: defaults to `tls`
- `STAGING_MAIL_PORT`: defaults to `587`
- `STAGING_AWS_USE_PATH_STYLE_ENDPOINT`: defaults to `false`
- `STAGING_SCOUT_PREFIX`: defaults to `staging_`
- `STAGING_DEFAULT_STORE_IDENTIFIER`: defaults to empty
- `STAGING_EDGE_URL`: defaults in the smoke runner to `http://127.0.0.1:${EDGE_PORT}`
- `STAGING_AWS_URL`: defaults to empty
- `STAGING_AWS_ENDPOINT`: defaults to empty

## Dispatch

Run **Staging Smoke** manually from GitHub Actions.

Recommended first dispatch:

- `target`: `ephemeral`
- `mode`: `validate`
- `backend_image`: a scanned backend image tag built after S3 filesystem support was added
- `storefront_image`: `ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-20260512-a1e913d`
- `runner`: `ubuntu-latest`, unless staging services are private-network only

Use `target=environment` with `mode=all` only when the runner can reach PostgreSQL, Redis, Meilisearch, object storage, SMTP, and any hostnames used by the edge smoke checks.

Use `target=ephemeral` with `mode=all` to prove the selected images and process topology before the environment secret contract is fully populated. It still requires a backend image built from a commit that includes `league/flysystem-aws-s3-v3`; older backend images fail the S3 storage readiness check.
