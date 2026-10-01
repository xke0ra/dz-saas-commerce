# Stage 12: Backup / Recovery / Observability

**Audit Date:** 2026-10-01
**Audit Branch:** `main`
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`

Evidence dir `docs/evidence/` contains exactly 3 files (all dated, historical): `STAGING_SMOKE_PROOF_2026-05-26_AR.md`, `BACKUP_RESTORE_DRILL_PROOF_2026-05-28.md` (PASS), `STAGING_POSTGRES_BACKUP_AUTOMATION_PROOF_2026-05-28.md` (PASS).

## 1. Capability matrix

| Capability | Verdict | Evidence |
|---|---|---|
| Postgres backup (staging) | IMPLEMENTED (staging only) | drill PASS + checksum, systemd timer active next run 2026-05-29; scripts `deploy/backup/bin/*.example`; runbook `BACKUP_RESTORE_RUNBOOK.md:15-21` |
| Restore | DOCUMENTED + drill-proven to tmp DB only | guarded drill (`ALLOW_STAGING_RESTORE`, dual-confirm); live DB never overwritten; recurring cadence + object-storage restore UNVERIFIED |
| Prod backup/PITR/encryption/offsite | DOCUMENTED, UNVERIFIED | `PRODUCTION_READINESS.md:460-467` |
| Rollback | DOCUMENTED, UNVERIFIED | `ROLLBACK_RUNBOOK.md:7` header "documented but not yet drill-proven" (ADR-0012 acceptance needs drill) |
| Queue worker | DOCUMENTED + staging-compose IMPLEMENTED, prod supervision UNVERIFIED | command + systemd example; failed-job handling present; latency/metrics/alerts missing (`QUEUE_SCHEDULER_RUNBOOK.md:19-25`) |
| Scheduler | DOCUMENTED + staging IMPLEMENTED, single-instance UNVERIFIED | `billing:process 02:00` + `checkout-idempotency:prune 03:00`; one-scheduler rule; heartbeat/locks missing |
| TLS | IMPLEMENTED staging via external Caddy; repo config DOCUMENTED only | edge listens 8080 plain; no cert automation in repo; TLS-expiry alert missing |
| Logs | IMPLEMENTED (stderr stack staging) | `LOG_STACK=stderr`; no aggregation/routing/dashboards |
| Metrics/monitoring/alerts | DOCUMENTED, largely UNVERIFIED | runbook matrix: liveness/readiness/DB/Redis/storage implemented; storefront/failed-jobs partial; 5xx/checkout/latency/scheduler/mail/TLS/backup-age missing; NO provider (ADR-0014 proposes Sentry, not adopted) |
| Health endpoints | IMPLEMENTED | `/api/system/health/live` + `/ready` (closures `routes/api.php:8-18`); backend image HEALTHCHECK; `system:health` command; readiness must not false-positive (invariant 20) |

## 2. Findings

- OPS-004 (MEDIUM, UNVERIFIED): no production monitoring/alerting/error-tracking provider; incidents would be detected by users before tooling. Blocks production, not migration.
- OPS-005 (MEDIUM, UNVERIFIED): rollback never drill-proven; restore drill only to tmp DB. First staging rollback drill required before any prod traffic.
- OPS-006 (LOW): audit-log retention policy undefined though PII (invitee emails) is written to `audit_logs` by design (`InviteTenantUser`).

## 3. Operations gate verdict

PASS WITH FINDINGS for staging-grade operations; NOT PROVEN for production. Historical evidence (May 2026) must not be read as current live health (per `AI_CONTEXT.md:67-68,215-216`).
