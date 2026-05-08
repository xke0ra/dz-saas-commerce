# Staging Deployment Skeleton

This folder defines the first single-host staging smoke topology. It consumes immutable images published by `.github/workflows/container-images.yml` and wires the same process shape described in `docs/adr/0012-production-deployment-topology.md`.

It is not a production installer. It is a repeatable staging proof target for:

- backend PHP-FPM
- backend queue worker
- backend scheduler
- storefront Next.js server
- Nginx edge proxy

## Prepare

Copy the examples and replace every placeholder with staging-only values:

```bash
cp deploy/staging/images.env.example deploy/staging/images.env
cp deploy/staging/backend.env.example deploy/staging/backend.env
cp deploy/staging/storefront.env.example deploy/staging/storefront.env
```

Set `BACKEND_IMAGE` and `STOREFRONT_IMAGE` to immutable tags or digests, for example `sha-<12-char-sha>`.

## Validate Compose

```bash
docker compose \
  --env-file deploy/staging/images.env \
  -f deploy/staging/docker-compose.staging.example.yml \
  config
```

## Deploy Smoke

```bash
docker compose \
  --env-file deploy/staging/images.env \
  -f deploy/staging/docker-compose.staging.example.yml \
  pull

docker compose \
  --env-file deploy/staging/images.env \
  -f deploy/staging/docker-compose.staging.example.yml \
  up -d
```

## Verify

```bash
docker compose \
  --env-file deploy/staging/images.env \
  -f deploy/staging/docker-compose.staging.example.yml \
  ps

curl -I http://127.0.0.1:${EDGE_PORT:-8080}
```

For a real staging environment, route TLS/load-balancer traffic to the edge service and run the reverse proxy checks in `docs/REVERSE_PROXY_RUNBOOK.md`.
