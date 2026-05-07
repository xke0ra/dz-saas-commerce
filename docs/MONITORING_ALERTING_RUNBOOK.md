# Monitoring And Alerting Runbook

Last updated: 2026-05-07

This runbook defines the first monitoring, alerting, logging, and error tracking contract for `dz-saas-commerce`.

It documents what can be monitored with the current codebase and what still requires an external monitoring stack. It does not claim that production monitoring is already implemented.

## Current Status

Implemented or available in the repository:

- backend liveness endpoint: `GET /api/system/health/live`
- backend readiness endpoint: `GET /api/system/health/ready`
- CLI health checks:
  - `php artisan system:health --scope=live --format=json`
  - `php artisan system:health --scope=ready --format=json`
- readiness checks for:
  - production runtime safeguards
  - PostgreSQL connectivity
  - Redis connectivity when configured
  - queue backend connectivity
  - storage disk connectivity
  - Meilisearch connectivity when search is enabled
- queue failed job inspection through `php artisan queue:failed`
- scheduler inspection through `php artisan schedule:list`
- queue and scheduler supervision runbook in `docs/QUEUE_SCHEDULER_RUNBOOK.md`
- backup/restore runbook in `docs/BACKUP_RESTORE_RUNBOOK.md`
- reverse proxy runbook in `docs/REVERSE_PROXY_RUNBOOK.md`
- Laravel logging channels already available through `backend/config/logging.php`:
  - `single`
  - `daily`
  - `stderr`
  - `syslog`
  - `papertrail`
  - `slack`

Not implemented yet:

- no selected error tracking provider
- no Sentry/Bugsnag/Rollbar/OpenTelemetry integration
- no production alert routing
- no uptime monitor configuration
- no log aggregation stack
- no metrics dashboard
- no queue latency metrics
- no backup failure alert
- no SSL/custom-domain expiry alert

## Logging Strategy

Local development:

- file logs are acceptable.
- avoid logging sensitive customer payloads.
- use local-only dummy credentials.

Container production:

- prefer `stderr`/stdout-compatible logging so the platform collector can capture logs.
- keep `LOG_LEVEL=info` or stricter unless diagnosing an incident.
- route logs to a centralized logging system through the runtime platform, agent, or supported Laravel channel.
- do not depend on local `storage/logs` as the only production log source.

Recommended backend production logging values:

```dotenv
LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=info
```

If a hosted logging provider is selected later, document the selected channel and required secret names without committing secret values.

## Error Tracking Strategy

Status: required before production; provider not selected.

Minimum provider requirements:

- supports PHP/Laravel and Next.js.
- separates `local`, `testing`, `staging`, and `production` environments.
- supports release/version tagging.
- supports alert routing.
- supports PII scrubbing before events leave the application.
- supports ignoring expected 404/validation/rate-limit noise.

Required context rules:

- include environment, release, request id, route, and job class when available.
- include tenant/store identifiers only when safe and necessary for triage.
- never send raw customer phone numbers, addresses, names, order notes, payment proof files, tokens, passwords, or secret configuration values.
- hash or redact sensitive identifiers when correlation is needed.

Do not add a provider package until the ADR or implementation ticket names the selected service and acceptance criteria.

## Uptime Checks

Minimum checks:

```bash
curl -fsS https://api.example.com/api/system/health/live
curl -fsS https://api.example.com/api/system/health/ready
```

Expected behavior:

- liveness should fail only when the Laravel process cannot boot.
- readiness should fail when required dependencies are unreachable.
- in production, readiness fails when `APP_DEBUG=true` or `APP_KEY` is missing.

Recommended staging/production checks:

- backend liveness every 1 minute.
- backend readiness every 1 minute.
- storefront homepage every 1 minute.
- storefront product listing every 5 minutes.
- track-order public route every 5 minutes with non-sensitive synthetic data only if a safe fixture exists.

## Queue And Scheduler Monitoring

Required process checks:

- queue worker process is running.
- scheduler process is running.
- exactly one scheduler instance runs per environment unless the deployment platform guarantees safe scheduler locks.

Useful commands:

```bash
cd backend
php artisan schedule:list
php artisan queue:failed
php artisan billing:process --sync
php artisan checkout-idempotency:prune --dry-run
```

Alert conditions before beta:

- queue worker down.
- scheduler down.
- failed jobs count greater than zero for billing, domain, notification, future payment, future shipping, or checkout jobs.
- repeated failures for the same job class.
- queue latency above the agreed threshold after metrics exist.

## Business-Critical Alerts

P0/P1 alerts before beta:

- readiness check failing.
- production runtime safeguard failing.
- queue worker down.
- scheduler down.
- billing lifecycle job failure.
- checkout failure or HTTP 5xx spike.
- checkout abuse/rate-limit spike after metrics are added.
- database unavailable.
- Redis unavailable when configured.
- Meilisearch unavailable when search is enabled.
- object storage write failure.
- backup failure after automated backups are configured.
- restore drill failure.
- TLS certificate expiry.
- custom domain verification/routing failures.

## Checkout Abuse Signals

Current checkout code logs suspicious events with hashes instead of raw phone/IP values.

Signals to aggregate later:

- repeated `Idempotency-Key` conflict.
- duplicate checkout replay.
- rate limit by IP.
- rate limit by phone.
- rate limit by store.
- elevated checkout validation failures.
- elevated order creation failures.

These should feed dashboards and alerts after a metrics/log aggregation tool is selected.

## Security And Privacy Rules

Logs and error events must not include:

- raw phone numbers.
- full names.
- addresses.
- order notes.
- payment proof files or private file URLs.
- invitation tokens.
- password reset tokens.
- API keys.
- database credentials.
- S3/Meilisearch/Mail credentials.

Before enabling production error tracking, verify:

- PII scrubber is active.
- request body capture is disabled or tightly filtered.
- headers such as `Authorization`, cookies, and CSRF tokens are redacted.
- tenant/store context does not expose another tenant's sensitive data.

## Incident Triage

First commands during an incident:

```bash
cd backend
php artisan system:health --scope=ready --format=json
php artisan queue:failed
php artisan schedule:list
```

Then inspect:

- application logs.
- reverse proxy logs.
- queue worker logs.
- scheduler logs.
- database health.
- Redis health.
- Meilisearch health.
- object storage health.
- recent deployments and migrations.

If a migration or deploy caused the incident, follow `docs/PRODUCTION_READINESS.md` and `docs/BACKUP_RESTORE_RUNBOOK.md` before attempting manual data changes.

## Minimum Dashboard

Before beta, create a dashboard with:

- backend liveness status.
- backend readiness status.
- storefront availability.
- HTTP 5xx rate.
- checkout success/failure rate.
- checkout rate-limit/conflict counts.
- failed jobs count by job class.
- queue latency after metrics exist.
- scheduler last successful tick.
- database availability.
- Redis availability when configured.
- Meilisearch availability when enabled.
- storage availability.
- latest backup status after automated backups are configured.

## Definition Of Done

Monitoring and alerting foundation is complete only when:

- production/staging uptime checks exist for liveness and readiness.
- queue worker and scheduler process alerts exist.
- failed jobs alerts exist for sensitive job categories.
- centralized logs are configured.
- error tracking provider is selected and integrated.
- PII redaction is verified.
- alert routing is tested.
- at least one test alert has been delivered and acknowledged.
- backup failure alert exists after automated backups are configured.
- runbooks link to the active dashboards and alert destinations.
