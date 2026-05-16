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

Before running a real environment smoke, use `docs/STAGING_READINESS_CHECKLIST_AR.md` as the short operational gate. It links back to this folder and the longer reverse proxy, queue/scheduler, and production readiness runbooks.

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
BACKEND_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/backend:staging-20260512-096bc05
STOREFRONT_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-20260512-096bc05
```

Container Images run `25756290200` also published `sha-096bc05a0773` and `staging` tags after Trivy image scanning passed.

Digests:

- backend: `sha256:ab68061bdbe14d6c545babb2981aa761a778369c92b7393be993fca326ba05cc`
- storefront: `sha256:d71c659acb567fd80623c2487494c01b4440683a25e38a64ab220b232ec8a358`

GitHub **Staging Smoke** passed with `target=ephemeral` and `mode=all` on run `25756545567` using these tags. A real staging smoke still requires staging-only `backend.env` and `storefront.env` values for PostgreSQL, Redis, Meilisearch, object storage, SMTP, domains, and app secrets.

## GitHub Smoke

The manual **Staging Smoke** workflow has two targets:

- `target=environment`: renders ignored staging env files from the GitHub `staging` environment, logs into GHCR, and calls `deploy/staging/staging-smoke.sh`.
- `target=ephemeral`: uses `deploy/staging/staging-ephemeral-smoke.sh` and the disposable service overlay.

Use `mode=validate` first to prove the selected contract and Compose rendering. Use `mode=all` for `target=environment` only when the selected runner can reach all staging backing services. If the services are private-network only, dispatch the workflow against a self-hosted runner with the required network access.

## Ephemeral Smoke

Use this path when real staging secrets or managed services are not ready yet:

```bash
BACKEND_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/backend:staging-20260512-096bc05 \
STOREFRONT_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-20260512-096bc05 \
deploy/staging/staging-ephemeral-smoke.sh all
```

For local development against a freshly built backend image, tag the image with a staging-style immutable tag and skip registry pulls:

```bash
docker buildx build --load -f backend/Dockerfile \
  -t ghcr.io/xke0ra/dz-saas-commerce/backend:staging-20260512-localtest backend

SKIP_PULL=1 \
BACKEND_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/backend:staging-20260512-localtest \
STOREFRONT_IMAGE=ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-20260512-096bc05 \
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

`verify` checks Compose process state, Laravel readiness, absence of failed jobs, the storefront edge response, and backend live/ready HTTP health through the edge proxy. HTTP checks use bounded curl timeouts. Use `deploy/staging/staging-smoke.sh all` for validate + pull + up + verify, and `deploy/staging/staging-smoke.sh down` to stop the stack.

For a real staging environment, route TLS/load-balancer traffic to the edge service and run the reverse proxy checks in `docs/REVERSE_PROXY_RUNBOOK.md`.
