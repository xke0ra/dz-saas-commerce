# Production Readiness Runbook

Last updated: 2026-05-27

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
- `.github/workflows/container-images.yml` GHCR image publish baseline
- `.github/workflows/staging-smoke.yml` manual staging smoke baseline
- repository secret hygiene check through `scripts/security/secret-hygiene.sh`
- shared container image scan policy through `scripts/security/container-image-scan.sh`
- backend S3 filesystem runtime support through `league/flysystem-aws-s3-v3`
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
- staging deployment skeleton in `deploy/staging/`
- self-contained ephemeral staging smoke overlay in `deploy/staging/docker-compose.staging.ephemeral.yml`
- staging deployment runbook, readiness checklist, and smoke proof template for real staging execution
- external mayfairs.app staging host exists on DigitalOcean with Caddy TLS in front of internal Nginx edge
- recorded external staging smoke proof for HTTPS, Filament/Livewire assets, mandatory 2FA setup/challenge, and a demo storefront

Still required:

- Keep the GitHub `staging` environment secret/variable contract aligned with the live staging host if the manual workflow is used
- Record a fresh external staging smoke proof after every application, proxy, DNS, or env change
- keep container image vulnerability scans green as base images and advisories change
- complete automated browser/e2e validation behind the reverse proxy and Cloudflare Proxied-mode decision
- staging automated backup schedules deployed and scheduled (2026-05-28; see `STAGING_POSTGRES_BACKUP_AUTOMATION_PROOF_2026-05-28.md`); restore drill scheduling and execution monitoring still pending for production
- queue and scheduler supervision deployment in staging/production
- error tracking integration
- real uptime checks, alert routing, and centralized log aggregation
- CSP tightening after staging/proxy browser and e2e validation
- Production hardening review after real staging proof

Latest local smoke verification: 2026-05-12.

- Backend smoke passed locally: `composer audit --no-interaction`, `php vendor/bin/pint --test`, `php artisan system:health --scope=ready --format=json`, and `php artisan test` (`154 passed`, `629 assertions`).
- Repository hygiene and clean export checks passed: `scripts/security/secret-hygiene.sh` and `scripts/release/clean-export-check.sh`.
- Storefront dependency audit passed after updating Next to `15.5.18`: `pnpm audit --audit-level moderate` reported no known vulnerabilities.
- Storefront local verification passed for `pnpm build`, sequential `pnpm typecheck`, and `pnpm test:e2e` (`6 passed`) on Next.js `15.5.18`.
- Storefront Docker verification passed on 2026-05-12: `./storefront/scripts/verify-docker.sh all` completed install/typecheck/build and Playwright e2e (`6 passed`).
- Dockerfile checks and local image build smoke passed on 2026-05-12 for backend and storefront through `docker buildx build --check` and `docker buildx build --load`.
- GitHub Actions Quality Gates passed on PR #1 / run `25743248405`: `Repository Hygiene`, `Backend`, `Storefront`, `Dockerfile Checks`, and `Storefront E2E`.
- Main branch protection now requires those five checks with strict status checks enabled and admin enforcement enabled.
- GitHub Actions Quality Gates also passed after merge on `main` / run `25744282999`.
- Container image publishing to GHCR passed on `main` / run `25751543062` for both backend and storefront, using the `staging` channel and `staging-20260512-a1e913d` tag after Trivy image scans passed.
- Container image publishing to GHCR passed again on `main` / run `25756290200` after the S3 filesystem fix, using tag `staging-20260512-096bc05`.
- Staging compose config validation passed locally with the published GHCR tags.
- The GitHub `staging` environment currently exists, but it has no configured secrets or variables as of 2026-05-12.
- Local Trivy `0.70.0` scan passed for the backend CI image with `--scanners vuln --ignore-unfixed --pkg-types os,library --severity CRITICAL,HIGH`.
- Local Trivy `0.70.0` scan passed for the storefront CI image after updating the runtime image's bundled npm to `11.14.1`.
- Local ephemeral staging smoke passed on 2026-05-12 with generated staging-only secrets, disposable PostgreSQL/Redis/Meilisearch/MinIO/Mailpit services, backend migrations, `StorefrontDemoSeeder`, queue/scheduler containers, edge proxy checks, failed-job check, storefront HTTP response, and backend live/ready HTTP health.
- That smoke used a locally rebuilt backend image tagged `ghcr.io/xke0ra/dz-saas-commerce/backend:staging-20260512-localtest`; Trivy `0.70.0` reported 0 OS and 0 Composer-vendor vulnerabilities for that image.
- The run exposed and closed a real production dependency gap: the previously published backend image did not include Laravel's S3 Flysystem adapter, so readiness failed at the `storage` check when `FILESYSTEM_DISK=s3`.
- GitHub **Staging Smoke** passed with `target=ephemeral` and `mode=all` on run `25756545567`, using the published `staging-20260512-096bc05` backend and storefront images.
- This verification proves the Compose/process contract against disposable backing services. It still does not prove an externally provisioned staging deployment, TLS/custom-domain routing, restore drills, alert routing, or production-grade secret management.

Latest backend verification for the deployed 2FA fix:

- `composer audit --no-interaction`: no security vulnerability advisories found.
- `php vendor/bin/pint --test`: PASS on 574 files.
- `php artisan test tests/Feature/Security/TwoFactorAuthenticationTest.php tests/Feature/Security/TwoFactorResetCommandTest.php`: 24 passed, 136 assertions.
- `php artisan test`: 292 passed, 1448 assertions.
- `backend/composer.lock` was updated to resolve Symfony/Laravel dependency advisories, including Laravel framework and Symfony component updates.

Documentation refresh on 2026-05-19:

- `docs/STAGING_DEPLOYMENT_RUNBOOK_AR.md`, `docs/STAGING_READINESS_CHECKLIST_AR.md`, and `docs/STAGING_SMOKE_PROOF_TEMPLATE_AR.md` define the next real staging execution path.
- This refresh did not run a new external staging smoke and did not change application code.
- This 2026-05-19 note is superseded by the 2026-05-26 external staging update below.

External staging update on 2026-05-26:

- DigitalOcean droplet `mayfair-vps` in FRA1 now hosts `/opt/mayfair`.
- Domains `mayfairs.app`, `api.mayfairs.app`, `admin.mayfairs.app`, and `www.mayfairs.app` resolve to `46.101.178.27`.
- Caddy terminates public HTTPS and forwards to the Docker Nginx edge bound to `127.0.0.1:8080`.
- Local images `mayfair-backend:local` and `mayfair-storefront:local` are used for this staging host.
- Commit `045c264` (`Fix mandatory Filament 2FA setup flow (#36)`) was deployed and manually smoke-tested.
- HTTPS checks returned HTTP/2 200 for `mayfairs.app`, `api.mayfairs.app`, and `admin.mayfairs.app`.
- Filament CSS/JS and Livewire script/module/update URLs generated HTTPS URLs; no mixed content was observed in the smoke.
- Mandatory 2FA setup and challenge passed on staging, including invalid TOTP rejection and safe no-op reset semantics for a target without enabled 2FA.
- A staging demo tenant/store is reachable at `https://mayfairs.app`, linked to `mayfairs.app`, and has COD, shipping rates, products, inventory, and storefront resolution.
- The proof is recorded in `docs/STAGING_SMOKE_PROOF_2026-05-26_AR.md`.
- This is an external staging foundation proof, not production readiness. Restore drill, backup automation, monitoring/alerting, log aggregation, release rollback proof, Cloudflare Proxied validation, and custom-domain/TLS automation beyond the primary mayfairs.app domains remain pending.

## Image Build Commands

Backend PHP-FPM image:

```bash
docker build -f backend/Dockerfile -t dz-saas-commerce-backend:local backend
```

Storefront Next.js image:

```bash
docker build -f storefront/Dockerfile -t dz-saas-commerce-storefront:local storefront
```

## Container Image Publishing

The publish workflow is `.github/workflows/container-images.yml`.

It builds, scans, and pushes both images to GHCR:

- `ghcr.io/<owner>/<repo>/backend`
- `ghcr.io/<owner>/<repo>/storefront`

Supported publishing paths:

- manual `workflow_dispatch` to the `staging` channel
- manual `workflow_dispatch` to the `production` channel, guarded so it must be dispatched from a Git tag
- automatic publish on tags matching `v*.*.*`

Latest proven staging publish on 2026-05-12:

- run: `25756290200`
- commit: `096bc05a077359dde63b6f77e7822aa8b45003c9`
- backend: `ghcr.io/xke0ra/dz-saas-commerce/backend:staging-20260512-096bc05`
- storefront: `ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-20260512-096bc05`
- also tagged by workflow as `sha-096bc05a0773` and `staging`
- backend digest: `sha256:ab68061bdbe14d6c545babb2981aa761a778369c92b7393be993fca326ba05cc`
- storefront digest: `sha256:d71c659acb567fd80623c2487494c01b4440683a25e38a64ab220b232ec8a358`

The earlier `staging-20260512-a1e913d` backend publish remains a valid scanned GHCR publish proof, but it predates the S3 filesystem dependency fix and is superseded for staging smoke use.

Each image receives:

- immutable `sha-<12-char-sha>` tag
- the requested manual tag, when provided
- channel tag: `staging`, `production`, or `release`

Do not treat mutable channel tags as rollback anchors. Staging and production deployment records should capture the exact immutable SHA tag or image digest used.

The workflow scans the locally built image before pushing any tag. A fixed `HIGH` or `CRITICAL` OS/library vulnerability blocks publication.

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

## Staging Skeleton

The first staging smoke target is in `deploy/staging/`.

It includes:

- `docker-compose.staging.example.yml`
- `docker-compose.staging.ephemeral.yml`
- `images.env.example`
- `backend.env.example`
- `storefront.env.example`
- `staging-smoke.sh`
- `staging-ephemeral-smoke.sh`
- `GITHUB_ENVIRONMENT.md`
- `README.md`

The compose file expects immutable backend and storefront images, then starts:

- backend PHP-FPM
- backend queue worker
- backend scheduler
- storefront Next.js server
- Nginx edge proxy

It does not create production-like managed backing services. Staging operators must provide PostgreSQL, Redis, Meilisearch, object storage, mail, and real secrets through copied local env files that are ignored by Git.

For runner-local proof before real staging services exist, `staging-ephemeral-smoke.sh` overlays disposable PostgreSQL, Redis, Meilisearch, MinIO, and Mailpit services. It generates temporary env files, migrates the database, seeds `StorefrontDemoSeeder`, and delegates validation/startup/verification to the same smoke runner. This is a staging contract proof, not a substitute for externally managed staging infrastructure.

The smoke runner validates that image references are immutable enough for a recorded staging proof, rejects placeholders in env files, renders the Compose config, pulls images, starts the stack, and verifies:

- Compose process state
- Laravel readiness through `php artisan system:health --scope=ready --format=json`
- failed jobs listing
- storefront edge HTTP response
- backend live and ready HTTP health through the edge proxy

The manual workflow `.github/workflows/staging-smoke.yml` supports two targets:

- `target=environment`: renders the ignored `deploy/staging/*.env` files from the GitHub `staging` environment, logs into GHCR, and delegates to the same smoke runner. Start with `mode=validate`, then run `mode=all` only from a runner that can reach the staging PostgreSQL, Redis, Meilisearch, object storage, SMTP, and edge hostnames.
- `target=ephemeral`: runs the disposable overlay on the selected runner. Use it as a fast proof of image/process compatibility before real staging services are ready.

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

- repository hygiene and clean export rehearsal for tracked env files, private keys, generated artifacts, and high-confidence secret patterns
- backend dependency install, Composer audit, Pint format check, migration smoke, readiness smoke, tests, and route listing
- storefront dependency install, pnpm audit at `moderate` or higher, typecheck, and production build
- Dockerfile syntax/build-plan checks via `docker buildx build --check`
- backend and storefront Docker image build smoke checks without pushing images
- backend and storefront container image vulnerability scans through `scripts/security/container-image-scan.sh`, using Trivy `0.70.0` and failing on fixed `HIGH` or `CRITICAL` OS/library vulnerabilities
- required Playwright e2e with artifact upload on failure

Limitations:

- The Quality Gates workflow is proven and required, and GHCR image publishing is proven for the staging channel. Downstream staging consumption still needs a real environment smoke.
- The image scan currently gates CI-built images and publication candidates. It does not yet attach SARIF/SBOM artifacts to the GitHub Security tab.
- The latest GitHub Actions run emitted Node.js 20 action runtime deprecation annotations for `actions/checkout@v4`, `actions/cache@v4`, and `actions/setup-node@v4`; this is not a current failure, but it should be watched before GitHub's Node 24 runner enforcement dates.

Required branch protection checks for `.github/workflows/quality.yml`:

- `Repository Hygiene`
- `Backend`
- `Storefront`
- `Dockerfile Checks`
- `Storefront E2E`

These checks are the documented quality gate contract. If branch protection is changed in GitHub, update this runbook instead of relying on stale status text.

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

Documented and improved (PR #43):

- `docs/BACKUP_RESTORE_RUNBOOK.md` with safer restore drill guidance
- `deploy/backup/bin/staging-restore-drill.sh.example` with multi-layered validation:
  - Enforces restore to temporary database only (naming pattern `dz_saas_restore_drill_*`)
  - Refuses to run unless `ALLOW_STAGING_RESTORE=true`
  - Rejects any URL appearing to reference production
  - Requires explicit dual confirmation for cleanup
- `deploy/backup/backup.env.example` documenting:
  - `STAGING_ADMIN_DATABASE_URL` for admin-only CREATE/DROP operations
  - `RESTORE_DRILL_DATABASE` for the temporary drill database name
  - `RESTORE_DRILL_DATABASE_URL` for full connection to the drill database
  - `CLEANUP_RESTORE_DRILL_DATABASE` and `CONFIRM_DROP_RESTORE_DRILL_DATABASE` for dual-confirmation cleanup

Completed (2026-05-28):

- Manual staging PostgreSQL restore drill executed against isolated temporary database.
- Backup verification: checksum passed; restore completed successfully.
- Verification queries confirmed data integrity (users, tenants, stores, products, orders).
- Temporary database cleaned up successfully.
- Evidence recorded in `docs/BACKUP_RESTORE_DRILL_PROOF_2026-05-28.md`.

Required before production:

- Deploy automated PostgreSQL backups from the provided examples or a managed provider.
- Deploy object storage backup/replication policy.
- Implement backup monitoring and alerting.
- Establish recurring restore drill cadence (at least monthly).
- Document and monitor backup completion/age.
- Implement backup encryption and access controls.

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

- Fresh smoke proof after any application/proxy/DNS/env change.
- Cloudflare Proxied mode validation before enabling it.
- Automated browser/e2e verification behind the proxy.
- Final CSP/HSTS tuning after proxy deployment.

## Minimum Beta Gate

Before beta:

- Docker images build or at least pass build-plan checks in CI, then build/push when registry is selected.
- `APP_DEBUG=false` and `APP_KEY` verified through readiness in staging/production.
- Queue worker and scheduler supervised in staging/production.
- Basic health/readiness checks exist.
- Backup and restore documented; restore drill executed at least once against an isolated temporary staging database and evidence recorded before beta.
- Reverse proxy deployed and verified in staging.
- Monitoring and alerting configured at least for readiness, failed jobs, queue/scheduler processes, and critical 5xx spikes.
- Security headers baseline exists.
- Playwright or equivalent smoke checks run reliably in CI.
