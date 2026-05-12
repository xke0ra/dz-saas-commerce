# Staging Deployment Skeleton

This folder defines the first single-host staging smoke topology. It consumes immutable images published by `.github/workflows/container-images.yml` and wires the same process shape described in `docs/adr/0012-production-deployment-topology.md`.

It is not a production installer. It is a repeatable staging proof target for:

- backend PHP-FPM
- backend queue worker
- backend scheduler
- storefront Next.js server
- Nginx edge proxy

GitHub Actions can run the same smoke contract through `.github/workflows/staging-smoke.yml` after the `staging` environment is populated. See `deploy/staging/GITHUB_ENVIRONMENT.md` for the required secret and variable names.

## Prepare

Copy the examples and replace every placeholder with staging-only values:

```bash
cp deploy/staging/images.env.example deploy/staging/images.env
cp deploy/staging/backend.env.example deploy/staging/backend.env
cp deploy/staging/storefront.env.example deploy/staging/storefront.env
```

Set `BACKEND_IMAGE` and `STOREFRONT_IMAGE` to immutable tags or digests, for example `sha-<12-char-sha>`.
`images.env.example` also points Compose at the copied `backend.env` and `storefront.env` files:

```dotenv
BACKEND_ENV_FILE=./backend.env
STOREFRONT_ENV_FILE=./storefront.env
```

Latest proven staging image publish, 2026-05-12:

```dotenv
BACKEND_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/backend:staging-20260512-b8ef243
STOREFRONT_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-20260512-b8ef243
```

The same workflow run also published `sha-b8ef2437f12b` and `staging` tags. The compose config was validated locally with the `staging-20260512-b8ef243` tags. A real staging smoke still requires staging-only `backend.env` and `storefront.env` values for PostgreSQL, Redis, Meilisearch, object storage, SMTP, domains, and app secrets.

## GitHub Smoke

The manual **Staging Smoke** workflow renders ignored staging env files from the GitHub `staging` environment, logs into GHCR, and calls `deploy/staging/staging-smoke.sh`.

Use `mode=validate` first to prove the secret/variable contract and Compose rendering. Use `mode=all` only when the selected runner can reach all staging backing services. If the services are private-network only, dispatch the workflow against a self-hosted runner with the required network access.

## Validate

The smoke runner fails before deployment if any env file still contains placeholder values or mutable channel-only image tags such as `:staging`.

```bash
deploy/staging/staging-smoke.sh validate
```

## Deploy Smoke

```bash
deploy/staging/staging-smoke.sh pull
deploy/staging/staging-smoke.sh up
```

## Verify

```bash
deploy/staging/staging-smoke.sh verify
```

`verify` checks Compose process state, Laravel readiness, failed jobs, the storefront edge response, and backend live/ready HTTP health through the edge proxy. Use `deploy/staging/staging-smoke.sh all` for validate + pull + up + verify, and `deploy/staging/staging-smoke.sh down` to stop the stack.

For a real staging environment, route TLS/load-balancer traffic to the edge service and run the reverse proxy checks in `docs/REVERSE_PROXY_RUNBOOK.md`.
