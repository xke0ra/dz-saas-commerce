# Monitoring Baseline Matrix

هذه matrix تساعد على اختيار monitoring provider لاحقاً بدون افتراض vendor محدد. لا تعني أن monitoring مفعل حالياً.

قواعد PII/logging:

- لا raw phone.
- لا raw IP.
- لا tokens أو cookies أو authorization headers.
- لا payment proof private URLs.
- استخدم masking أو hashing عند الحاجة للربط التشغيلي.

| Item | Status | Current Source | Suggested Threshold | Severity | Action On Failure | Docs/Runbook |
|---|---|---|---|---|---|---|
| Backend live | available | `/api/system/health/live`, `php artisan system:health --scope=live` | fail for 2 consecutive 1m checks | P0 | restart/roll back app process, inspect deploy logs | `docs/MONITORING_ALERTING_RUNBOOK.md`, `docs/PRODUCTION_READINESS.md` |
| Backend ready | available | `/api/system/health/ready`, `php artisan system:health --scope=ready` | fail for 1-2 consecutive 1m checks | P0 | inspect database/cache/queue/storage/search; freeze deploys if persistent | `docs/MONITORING_ALERTING_RUNBOOK.md` |
| Storefront availability | partial | HTTP smoke through edge/storefront URL | non-2xx/3xx for 2 consecutive 1m checks | P0 | check Next.js process, edge routing, backend API base URL | `docs/REVERSE_PROXY_RUNBOOK.md` |
| Backend 5xx rate | external required | proxy/app logs, provider metrics | >1% for 5m or any sustained spike | P0 | inspect release, Laravel logs, database, queue pressure | `docs/MONITORING_ALERTING_RUNBOOK.md` |
| Storefront 5xx rate | external required | edge/platform logs | >1% for 5m | P0 | inspect Next.js runtime, upstream API, edge logs | `docs/MONITORING_ALERTING_RUNBOOK.md` |
| Checkout failure rate | external required | sanitized logs/metrics around checkout validation and order creation | abnormal spike over baseline or sustained 5xx | P0 | check backend readiness, inventory/payment/shipping errors, rate limits | `docs/STOREFRONT_CART.md`, `docs/MONITORING_ALERTING_RUNBOOK.md` |
| Failed jobs | partial | `php artisan queue:failed`, staging smoke check | count > 0 for billing/domain/payment/shipping/notification jobs | P0 | inspect failed job class, fix root cause, retry or forget after review | `docs/QUEUE_SCHEDULER_RUNBOOK.md` |
| Queue latency | missing | no metric source yet | warning >60s, critical >300s after baseline | P1 | scale workers only after root cause review; inspect Redis | `docs/QUEUE_SCHEDULER_RUNBOOK.md` |
| Scheduler last run | missing | process supervision and future heartbeat/log metric | no successful tick in 5m | P0 | ensure exactly one scheduler process, inspect logs | `docs/QUEUE_SCHEDULER_RUNBOOK.md` |
| Database connectivity | available | readiness database check | any ready failure | P0 | inspect PostgreSQL health, network, credentials, connection pool | `docs/PRODUCTION_READINESS.md` |
| Redis/cache connectivity | available when configured | readiness redis/cache checks | any ready failure when Redis is required | P0 | inspect Redis health, password, network, queue/cache config | `docs/MONITORING_ALERTING_RUNBOOK.md` |
| Meilisearch readiness | available when enabled | readiness search check | any ready failure when `SCOUT_DRIVER=meilisearch` | P1 | inspect Meilisearch health/key/indexing jobs | `docs/MONITORING_ALERTING_RUNBOOK.md` |
| Object storage readiness | available | readiness storage write/read/delete check | any ready failure | P0 | inspect bucket credentials, endpoint, permissions, disk config | `docs/BACKUP_RESTORE_RUNBOOK.md`, `docs/PRODUCTION_READINESS.md` |
| Mail failures | external required | mail provider logs, queue failures, future app metrics | failed send spike or provider rejection | P1 | pause affected notifications, inspect SMTP/provider status | `docs/MONITORING_ALERTING_RUNBOOK.md` |
| TLS expiry | external required | uptime/TLS monitor | warning <21 days, critical <7 days | P0 | renew cert, inspect proxy/load balancer automation | `docs/REVERSE_PROXY_RUNBOOK.md` |
| Backup age | external required | backup job logs/object metadata/provider metrics | warning >24h, critical >36h for daily backup | P0 | freeze destructive migrations, run backup manually, fix scheduler/provider | `docs/BACKUP_RESTORE_RUNBOOK.md` |
| Error tracking | missing | provider not selected | P0/P1 alerts routed by severity after integration | P1 | select provider via ADR/task, enable PII scrubber before production | `docs/MONITORING_ALERTING_RUNBOOK.md` |
| PII/log redaction | partial | documented policy; implementation provider-dependent | any raw phone/IP/token/private URL in logs is incident | P0 | redact source, rotate exposed secret if needed, document incident | `docs/SECURITY_BASELINE.md`, `docs/MONITORING_ALERTING_RUNBOOK.md` |
