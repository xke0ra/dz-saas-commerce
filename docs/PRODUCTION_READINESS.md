# Production Readiness Runbook

Last updated: 2026-05-07

This runbook records the production foundation for `dz-saas-commerce`. It is not a deployment guarantee yet; it defines the required operating contract before beta or production.

## Current Status

Implemented in this foundation pass:

- `backend/Dockerfile`
- `backend/.dockerignore`
- `backend/.env.production.example`
- `backend/docker/php-production.ini`
- `storefront/Dockerfile`
- `storefront/.dockerignore`
- `storefront/.env.production.example`
- root `.dockerignore`
- `.github/workflows/quality.yml` CI baseline
- backend liveness/readiness endpoints
- `php artisan system:health`
- backend Docker `HEALTHCHECK` using liveness scope
- production runtime safeguard checks for `APP_DEBUG` and `APP_KEY`
- backend security headers middleware
- storefront security headers through Next.js `headers()`
- scheduled checkout idempotency pruning command
- documented backup/restore runbook
- example backup automation scripts and systemd timers
- documented reverse proxy runbook and Nginx edge example
- Laravel trusted proxy config and tests
- documented queue/scheduler supervision runbook and systemd examples
- documented monitoring/alerting runbook
- production logging example routes Laravel logs to `stderr` for container collection

Still required:

- Prove CI inside GitHub Actions on the real repository root
- CI build jobs for both images when registry/promotion strategy is selected
- image vulnerability scanning
- reverse proxy deployment in staging and TLS/custom-domain validation
- deploy automated backup schedules and execute restore drill
- queue and scheduler supervision deployment in staging/production
- error tracking integration
- real uptime checks, alert routing, and centralized log aggregation
- CSP tightening after browser/e2e validation

## Image Build Commands

Backend PHP-FPM image:

```bash
docker build -f backend/Dockerfile -t dz-saas-commerce-backend:local backend
```

Storefront Next.js image:

```bash
docker build -f storefront/Dockerfile -t dz-saas-commerce-storefront:local storefront
```

## Runtime Topology

Recommended production topology:

- Reverse proxy/TLS layer in front of all public traffic.
- Storefront Next.js service for customer-facing pages.
- Backend PHP-FPM service behind an HTTP server or application gateway for Laravel API and Filament panels.
- Queue worker service using the same backend image.
- Scheduler service using the same backend image.
- Managed PostgreSQL.
- Managed Redis.
- Managed S3-compatible object storage.
- Managed or separately operated Meilisearch.
- Centralized logs and error tracking.

## Environment Separation

Required environments:

- `local`: Docker Compose services and dummy local credentials.
- `testing`: isolated PostgreSQL database, array/cache/sync queues where appropriate.
- `staging`: production-like infrastructure with non-production secrets and realistic deployment flow.
- `production`: least-privilege credentials, backups, monitoring, and `APP_DEBUG=false`.

Production env examples:

- `backend/.env.production.example`
- `storefront/.env.production.example`

Never commit real production secrets.

## CI Quality Gates

The baseline workflow is `.github/workflows/quality.yml`.

It currently checks:

- backend dependency install, migration smoke, readiness smoke, tests, and route listing
- storefront dependency install, typecheck, and production build
- Dockerfile syntax/build-plan checks via `docker buildx build --check`
- optional Playwright e2e with artifact upload on failure

Limitations:

- The workspace root is a Git repository, but this workflow is not yet proven as an active required pull request gate in GitHub Actions.
- It does not yet build/push production images.
- It does not yet run dependency vulnerability scanning.
- E2E remains optional until Playwright dependencies and integration strategy are stable.

## Backend Runtime Processes

Detailed runbook:

- `docs/QUEUE_SCHEDULER_RUNBOOK.md`

Web process:

```bash
php-fpm
```

Queue worker process:

```bash
php artisan queue:work redis --tries=3 --timeout=90 --sleep=3 --max-time=3600
```

Scheduler process:

```bash
php artisan schedule:work
```

Scheduled commands currently registered:

- `billing:process` daily at 02:00
- `checkout-idempotency:prune` daily at 03:00

Example systemd units:

- `deploy/supervision/systemd/dz-saas-commerce-queue.service.example`
- `deploy/supervision/systemd/dz-saas-commerce-scheduler.service.example`

Deployment/migration operator command:

```bash
php artisan migrate --force
```

Do not run migrations automatically from the container entrypoint until a deployment procedure with rollback and backup rules exists.

## Health And Readiness

Implemented:

- `GET /api/system/health/live`
- `GET /api/system/health/ready`
- `php artisan system:health --scope=live --format=json`
- `php artisan system:health --scope=ready --format=json`
- backend Docker image has a liveness `HEALTHCHECK`

Readiness checks:

  - production runtime safeguards
  - PostgreSQL connectivity
  - Redis connectivity
  - queue backend connectivity
  - storage disk connectivity
  - Meilisearch connectivity when search is enabled

Local smoke command:

```bash
php artisan system:health --scope=ready --format=json
```

Notes:

- liveness proves the Laravel process can boot.
- readiness proves required runtime dependencies are reachable.
- in production, readiness fails if `APP_DEBUG=true` or `APP_KEY` is missing.
- Redis and Meilisearch are skipped when the current configuration does not require them.
- The endpoints intentionally return operational status only, not secrets or connection strings.

## Failed Jobs

Required operations:

- Monitor failed jobs count.
- Alert on repeated billing, domain verification, notification, or checkout-related job failures.
- Define retry and manual resolution procedures.
- Restart queue workers after deploy with `php artisan queue:restart`.

Useful commands:

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

Use `queue:flush` only after an operator decision; it destroys failure evidence.

## Monitoring, Alerting, And Logs

Detailed runbook:

- `docs/MONITORING_ALERTING_RUNBOOK.md`

Current documented baseline:

- liveness/readiness checks are available for uptime monitors.
- `system:health` can be used by operators and CI smoke checks.
- queue/scheduler/failed-job checks are documented.
- `backend/.env.production.example` uses `LOG_STACK=stderr` so container platforms can collect Laravel logs.

Still required before production:

- select and integrate an error tracking provider.
- configure staging/production uptime checks.
- configure centralized log aggregation.
- configure alert routing and escalation.
- verify PII redaction in logs and error events.
- add alerts for failed jobs, queue/scheduler process failure, readiness failure, backup failure, and TLS/custom-domain issues.

## Storage Strategy

Local:

- Laravel local/public disks.
- MinIO for S3-compatible local testing.

Production:

- S3-compatible object storage.
- Separate public assets from private payment/support files where possible.
- Use least-privilege credentials.
- Define lifecycle/retention rules.
- Avoid public visibility for sensitive uploads.

## Meilisearch Notes

Production requirements:

- Strong master key.
- Network access restricted to backend services.
- Index prefix per environment.
- Rebuild procedure for product indexes.
- Monitoring for search latency and failed indexing jobs.

## Redis Notes

Production Redis should support:

- cache
- queues
- sessions
- maintenance mode store

Use separate logical databases or prefixes per environment when sharing infrastructure.

## Database Migration Procedure

Before migration:

1. Confirm latest backup completed.
2. Check pending migrations.
3. Review destructive migrations manually.
4. Put application in maintenance mode when required.

During deployment:

```bash
php artisan migrate --force
```

After migration:

1. Run smoke checks.
2. Check logs.
3. Check failed jobs.
4. Disable maintenance mode if enabled.

## Backup And Restore

Documented:

- `docs/BACKUP_RESTORE_RUNBOOK.md`

Required before production:

- Automated PostgreSQL backups deployed from the provided examples or a managed provider.
- Object storage backup/replication policy deployed.
- Execute and record restore procedure against staging.
- Restore drill against staging at least once.
- Backup encryption and access controls.

## Maintenance Mode And Store Availability

Laravel maintenance mode should be used for platform-level maintenance.

Store-level unavailability is domain logic and already exists conceptually through store/tenant status. Production runbooks must distinguish:

- platform maintenance
- tenant suspended
- store draft/disabled
- domain verification failure

## Reverse Proxy Strategy

Documented:

- `docs/REVERSE_PROXY_RUNBOOK.md`
- `deploy/reverse-proxy/nginx-edge.conf.example`
- `backend/config/trustedproxy.php`

Implemented baseline:

- Nginx example routes Laravel backend hosts to PHP-FPM on `backend:9000`.
- Nginx example routes storefront/custom domains to Next.js on `storefront:3000`.
- Forwarded headers are documented and passed in the example config.
- `TRUSTED_PROXIES` is documented in backend env examples.
- Nginx example syntax was checked with Docker using temporary host aliases.

Still required:

- Staging deployment.
- TLS termination validation.
- Custom domain routing validation.
- Browser/e2e verification behind the proxy.
- Final CSP/HSTS tuning after proxy deployment.

## Minimum Beta Gate

Before beta:

- Docker images build or at least pass build-plan checks in CI, then build/push when registry is selected.
- `APP_DEBUG=false` and `APP_KEY` verified through readiness in staging/production.
- Queue worker and scheduler supervised in staging/production.
- Basic health/readiness checks exist.
- Backup and restore documented; restore drill executed at least once.
- Reverse proxy deployed and verified in staging.
- Monitoring and alerting configured at least for readiness, failed jobs, queue/scheduler processes, and critical 5xx spikes.
- Security headers baseline exists.
- Playwright or equivalent smoke checks run reliably in CI.
