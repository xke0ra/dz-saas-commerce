# Rollback Runbook

Last updated: 2026-05-31

This runbook defines the procedure for rolling back a failed deployment of `dz-saas-commerce`. It covers code rollback, database considerations, and service restoration.

> **Status:** Staging rollback procedure is documented but not yet drill-proven. A rollback drill is required as part of ADR 0012 acceptance criteria before production launch.

---

## When To Roll Back

Roll back when:

- The deployment caused a P0 or P1 incident that cannot be fixed forward quickly.
- Readiness checks are failing after deployment and the root cause is the new release.
- A migration caused data corruption or application errors.
- The new release introduced a security regression.

Do not roll back for:

- P2/P3 issues that can be fixed in a follow-up commit.
- Missing features (roll forward instead).
- Configuration issues that can be fixed via environment variable change without a code rollback.

---

## Pre-Rollback Checklist

Before rolling back, complete these steps:

```bash
# 1. Confirm the current release is the problem
curl -fsS https://api.mayfairs.app/api/system/health/ready
php artisan system:health --scope=ready --format=json

# 2. Capture current logs before restarting anything
docker logs {backend_container} --since 30m > /tmp/incident-$(date -u +%Y%m%dT%H%M%SZ)-backend.log
docker logs {storefront_container} --since 30m > /tmp/incident-$(date -u +%Y%m%dT%H%M%SZ)-storefront.log

# 3. Identify the last known-good image tag
# Check CHANGELOG.md or CI deployment history for the previous image SHA
# Example: ghcr.io/owner/dz-saas-commerce/backend:staging-20260526-045c264

# 4. Confirm a recent database backup exists and is not older than 24h
# Check docs/evidence/ for last backup proof or inspect backup storage
```

---

## Code Rollback — Docker Compose Deployment

### Step 1 — Identify Previous Image

```bash
# On the deployment host, check the current docker-compose.yml image tags
cat /srv/dz-saas-commerce/docker-compose.yml | grep image:

# Or check the CI run history for the previous passing build
# The CHANGELOG.md records which image SHA was deployed to staging
```

### Step 2 — Update Image Tags

Edit `docker-compose.yml` on the deployment host to point to the previous known-good image:

```yaml
# Example: revert to previous SHA
services:
  backend:
    image: ghcr.io/owner/dz-saas-commerce/backend:PREVIOUS-SHA
  storefront:
    image: ghcr.io/owner/dz-saas-commerce/storefront:PREVIOUS-SHA
```

### Step 3 — Pull and Restart

```bash
cd /srv/dz-saas-commerce
docker compose pull
docker compose up -d --no-deps backend storefront
```

### Step 4 — Verify

```bash
# Health check
curl -fsS https://api.mayfairs.app/api/system/health/ready

# Storefront smoke
curl -fsS https://mayfairs.app

# Check for errors in restarted containers
docker logs backend --since 5m
```

---

## Database Migration Rollback

### Case 1 — Migration Added a Column (Safe to Roll Back)

If the new migration only added a nullable column and no data has been written to it:

```bash
cd backend
php artisan migrate:rollback --step=1
```

Verify:

```bash
php artisan migrate:status
php artisan system:health --scope=ready --format=json
```

### Case 2 — Migration Dropped or Renamed a Column (Destructive)

**Stop immediately.** A destructive migration that has run cannot be undone safely without restoring from backup.

```bash
# 1. Freeze all traffic (stop backend)
docker compose stop backend

# 2. Do NOT run migrate:rollback — it will not recover dropped data

# 3. Restore from the pre-deployment backup
# Follow docs/BACKUP_RESTORE_RUNBOOK.md — Restore Drill To Temporary Staging Database

# 4. Once backup restore is confirmed, swap the database and restart
```

### Case 3 — Migration Is Safe But Application Has a Bug

Roll back application code only (Step above). The migration stays applied. Write a compensating migration if needed.

---

## Estimating Rollback Safety

Before running `migrate:rollback`, answer these questions:

| Question | If YES → | If NO → |
|----------|---------|---------|
| Does the previous code version support the current DB schema? | Safe to roll back code only | Do NOT roll back — the old code may fail against the new schema |
| Did the migration drop or rename columns? | Restore from backup | Code-only rollback may work |
| Has data been written to new columns? | Restore from backup or roll forward | Code-only rollback may work |
| Is the migration reversible (`down()` is implemented)? | `migrate:rollback` is possible | Manual rollback required |

---

## Communication During Rollback

1. Post in incident channel immediately: `[ROLLBACK IN PROGRESS] {release} → {previous release} — {time UTC}`
2. Do not communicate externally until rollback is confirmed.
3. After rollback: `[ROLLBACK COMPLETE] Reverted to {previous release} — {time UTC}`
4. Open a post-mortem within 48 hours. See `docs/operations/INCIDENT_RESPONSE.md`.

---

## After Rollback

```bash
# 1. Full readiness verification
php artisan system:health --scope=ready --format=json

# 2. Check failed jobs (rollback may have left partial jobs)
php artisan queue:failed

# 3. Run a storefront smoke (visit home, product, attempt checkout in staging)

# 4. Confirm billing scheduler is running
php artisan schedule:list

# 5. Document the incident in docs/evidence/INCIDENT_POSTMORTEM_{date}.md
```

---

## Rollback Drill Requirement

A rollback drill must be executed before ADR 0012 can move from `Proposed` to `Accepted`.

Drill steps:

1. Deploy a known version to staging.
2. Simulate a bad release by deploying the version immediately after.
3. Trigger the rollback procedure above.
4. Verify readiness passes after rollback.
5. Record the drill in `docs/evidence/`.

---

## What Cannot Be Rolled Back

- Emails, SMS, or notifications already sent to customers.
- Billing charges already processed (when a payment gateway is integrated).
- Audit log entries (append-only by design — do not delete them).
- Data already shared with third-party services (shipping carriers, etc.).

For these, write compensating actions instead of trying to undo the past.
