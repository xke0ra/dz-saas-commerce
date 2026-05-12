# Staging Deployment Skeleton

This folder defines the first single-host staging smoke topology. It consumes immutable images published by `.github/workflows/container-images.yml` and wires the same process shape described in `docs/adr/0012-production-deployment-topology.md`.

It is not a production installer. It is a repeatable staging proof target for:

- backend PHP-FPM
- backend queue worker
- backend scheduler
- storefront Next.js server
- Nginx edge proxy

GitHub Actions can run the same smoke contract through `.github/workflows/staging-smoke.yml` after the `staging` environment is populated. See `deploy/staging/GITHUB_ENVIRONMENT.md` for the required secret and variable names.

The folder also includes an ephemeral overlay for runner-local proof before managed staging services exist. That path starts disposable PostgreSQL, Redis, Meilisearch, MinIO, and Mailpit services, generates temporary staging env files, runs migrations and `StorefrontDemoSeeder`, then executes the same readiness and edge checks.

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

Latest scanned staging image publish, 2026-05-12:

```dotenv
BACKEND_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/backend:staging-20260512-a1e913d
STOREFRONT_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-20260512-a1e913d
```

The same workflow run also published `sha-a1e913db7d4c` and `staging` tags after Trivy image scanning passed. A real staging smoke should use the scanned `staging-20260512-a1e913d` or `sha-a1e913db7d4c` tags. It still requires staging-only `backend.env` and `storefront.env` values for PostgreSQL, Redis, Meilisearch, object storage, SMTP, domains, and app secrets.

Important: the backend image above predates the S3 filesystem adapter fix discovered by the ephemeral smoke. It remains proof that GHCR publishing and scanning worked, but do not use it as the final S3-backed staging smoke image. Publish a new scanned backend image from the fixed commit, then record that tag/digest here.

## GitHub Smoke

The manual **Staging Smoke** workflow has two targets:

- `target=environment`: renders ignored staging env files from the GitHub `staging` environment, logs into GHCR, and calls `deploy/staging/staging-smoke.sh`.
- `target=ephemeral`: uses `deploy/staging/staging-ephemeral-smoke.sh` and the disposable service overlay.

Use `mode=validate` first to prove the selected contract and Compose rendering. Use `mode=all` for `target=environment` only when the selected runner can reach all staging backing services. If the services are private-network only, dispatch the workflow against a self-hosted runner with the required network access.

## Ephemeral Smoke

Use this path when real staging secrets or managed services are not ready yet:

```bash
BACKEND_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/backend:staging-YYYYMMDD-<fixed-sha> \
STOREFRONT_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-20260512-a1e913d \
deploy/staging/staging-ephemeral-smoke.sh all
```

For local development against a freshly built backend image, tag the image with a staging-style immutable tag and skip registry pulls:

```bash
docker buildx build --load -f backend/Dockerfile \
  -t ghcr.io/xke0ra/dz-saas-commerce/backend:staging-20260512-localtest backend

SKIP_PULL=1 \
BACKEND_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/backend:staging-20260512-localtest \
STOREFRONT_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-20260512-a1e913d \
deploy/staging/staging-ephemeral-smoke.sh all
```

The local proof completed on 2026-05-12 with S3 storage readiness passing against MinIO after adding `league/flysystem-aws-s3-v3` to the backend production dependencies.

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
