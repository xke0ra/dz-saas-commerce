# Queue And Scheduler Runbook

Last updated: 2026-05-07

This runbook defines the first operating contract for Laravel queues and the scheduler in `dz-saas-commerce`. It documents how these processes should be supervised; it does not prove that production supervision is already deployed.

## Current Status

Implemented:

- Queue worker command is documented.
- Scheduler command is documented.
- Scheduled commands are registered in Laravel:
  - `billing:process` daily at 02:00.
  - `checkout-idempotency:prune` daily at 03:00.
- Example systemd units exist under `deploy/supervision/systemd/`.
- Local smoke commands are documented below.

Not yet proven:

- Queue worker supervision in staging/production.
- Scheduler supervision in staging/production.
- Failed-job alerting.
- Queue latency metrics.
- Retry/dead-letter operating policy for each job type.

## Process Model

Run these as separate long-running processes, not inside the web PHP-FPM process:

- web: `php-fpm`
- queue worker: `php artisan queue:work redis --tries=3 --timeout=90 --sleep=3 --max-time=3600`
- scheduler: `php artisan schedule:work`

Container deployments should run separate services using the same backend image with different commands. VM deployments can use systemd or Supervisor.

## Queue Worker

Recommended command:

```bash
cd /var/www/dz-saas-commerce/backend
php artisan queue:work redis --tries=3 --timeout=90 --sleep=3 --max-time=3600
```

Why `--max-time=3600`:

- forces periodic process recycling
- picks up deployed code/config changes after restart
- limits long-lived memory growth

Operational rules:

- Run at least one worker before beta.
- Increase worker count only after measuring queue latency.
- Keep `--timeout` lower than the process supervisor stop timeout.
- Do not run queue workers with `APP_DEBUG=true` in production.
- Restart workers after deploy with `php artisan queue:restart`.

## Scheduler

Recommended command:

```bash
cd /var/www/dz-saas-commerce/backend
php artisan schedule:work
```

The scheduler must run once per application environment. Do not run multiple independent schedulers for the same environment unless commands are explicitly safe with locks.

Current scheduled commands:

```bash
php artisan schedule:list
```

Expected important entries:

- `billing:process` at `02:00`.
- `checkout-idempotency:prune` at `03:00`.

## Systemd Examples

Example unit files:

- `deploy/supervision/systemd/dz-saas-commerce-queue.service.example`
- `deploy/supervision/systemd/dz-saas-commerce-scheduler.service.example`

Install flow on a VM:

```bash
sudo cp deploy/supervision/systemd/dz-saas-commerce-queue.service.example /etc/systemd/system/dz-saas-commerce-queue.service
sudo cp deploy/supervision/systemd/dz-saas-commerce-scheduler.service.example /etc/systemd/system/dz-saas-commerce-scheduler.service
sudo systemctl daemon-reload
sudo systemctl enable --now dz-saas-commerce-queue
sudo systemctl enable --now dz-saas-commerce-scheduler
```

Check status:

```bash
systemctl status dz-saas-commerce-queue
systemctl status dz-saas-commerce-scheduler
journalctl -u dz-saas-commerce-queue -n 100 --no-pager
journalctl -u dz-saas-commerce-scheduler -n 100 --no-pager
```

Adjust paths, user, group, and PHP binary before use.

## Container-Orchestrated Deployments

If using Docker Compose, Nomad, Kubernetes, ECS, or another orchestrator:

- run the same backend image for web, worker, and scheduler
- override the command per process
- keep one scheduler replica per environment
- set restart policy to restart on failure
- route logs to centralized logging
- set memory/CPU limits for workers

Example logical commands:

```bash
php-fpm
php artisan queue:work redis --tries=3 --timeout=90 --sleep=3 --max-time=3600
php artisan schedule:work
```

## Failed Jobs Handling

Inspection:

```bash
php artisan queue:failed
```

Retry after the root cause is fixed:

```bash
php artisan queue:retry <id>
php artisan queue:retry all
```

Delete one failed job after operator review:

```bash
php artisan queue:forget <id>
```

Flush all failed jobs only after an explicit incident decision:

```bash
php artisan queue:flush
```

High-risk job categories:

- billing lifecycle jobs
- domain verification jobs
- notification jobs
- future payment/shipping webhook jobs
- checkout-related jobs if added later

## Alerting Direction

Before beta, alert on:

- queue worker process down
- scheduler process down
- failed jobs count greater than zero for billing/domain/payment/shipping jobs
- repeated failures for the same job class
- queue latency above an agreed threshold

The current repository does not yet include monitoring integration. This runbook defines what must be wired into the chosen monitoring stack.

## Deploy Procedure

Before deploy:

1. Confirm readiness is healthy.
2. Confirm the queue worker is running.
3. Confirm the scheduler is running.
4. Check failed jobs.

During deploy:

1. Put the app in maintenance mode only when needed.
2. Run migrations through the deployment procedure.
3. Restart PHP-FPM/web containers.
4. Run `php artisan queue:restart`.
5. Ensure the scheduler process is still running.

After deploy:

1. Run `php artisan system:health --scope=ready --format=json`.
2. Run `php artisan schedule:list`.
3. Check failed jobs.
4. Check worker/scheduler logs.

## Local Smoke

```bash
cd backend
php artisan schedule:list
php artisan queue:failed
php artisan billing:process --sync
php artisan checkout-idempotency:prune --dry-run
```

## Definition Of Done

This phase is complete only when:

- worker and scheduler are supervised in staging
- one scheduler instance per environment is guaranteed
- failed jobs are monitored and alerted
- deploy runbook includes `queue:restart`
- operator can inspect, retry, forget, and flush failed jobs safely
- logs for worker/scheduler are centralized
