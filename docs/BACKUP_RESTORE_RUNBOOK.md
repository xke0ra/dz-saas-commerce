# Backup And Restore Runbook

Last updated: 2026-05-31

This runbook defines the operational backup and restore contract for `dz-saas-commerce`.

## Current Status

### Proven (staging)

- PostgreSQL backup and restore procedure is documented and tested.
- S3-compatible object storage backup direction is documented.
- Restore drill checklist is documented.
- Pre-migration backup requirement is linked to production readiness.
- Example PostgreSQL backup script: `deploy/backup/bin/postgres-backup.sh.example`.
- Example object storage backup sync script: `deploy/backup/bin/object-storage-sync.sh.example`.
- Example staging restore drill script with safety guards: `deploy/backup/bin/staging-restore-drill.sh.example`.
- Example systemd backup services/timers: `deploy/backup/systemd/`.
- Example backup environment template: `deploy/backup/backup.env.example`.
- **Staging PostgreSQL backup automation installed and scheduled** via systemd timer on `mayfair-vps` (2026-05-28). See `docs/evidence/STAGING_POSTGRES_BACKUP_AUTOMATION_PROOF_2026-05-28.md`.
- **Staging restore drill executed and passed** (2026-05-28) — isolated temporary database, checksum verified, read-only verification queries passed, drill database dropped cleanly. See `docs/evidence/BACKUP_RESTORE_DRILL_PROOF_2026-05-28.md`.

### Not yet proven (required before production)

- Automated production backup schedule deployment (distinct from staging).
- Managed PostgreSQL point-in-time recovery configuration.
- Object storage replication/lifecycle policy.
- Encrypted offsite backup storage.
- Backup monitoring and failed-backup alerting.
- Recurring restore drill cadence (monthly or before major changes).
- Off-host/off-provider backup retention policy documented and enforced.

## Backup Scope

Back up these data sources:

- PostgreSQL primary database.
- S3-compatible object storage used by Laravel disks.
- Meilisearch indexes can be rebuilt from PostgreSQL; they are not the source of truth.
- `.env` secrets must not be stored in normal backup artifacts. Use the secret manager backup/export process instead.

Do not treat these as backups:

- Git repository history.
- Docker images.
- Local developer `.env` files.
- `storage/logs`.
- CI artifacts.

## PostgreSQL Backup

Use PostgreSQL custom format for database backups so restore can use `pg_restore`.

Example:

```bash
export BACKUP_DIR=/secure/backups/dz-saas-commerce
export BACKUP_NAME=dz_saas_commerce_$(date -u +%Y%m%dT%H%M%SZ).dump

mkdir -p "$BACKUP_DIR"

pg_dump \
  --format=custom \
  --verbose \
  --no-owner \
  --no-acl \
  --file="$BACKUP_DIR/$BACKUP_NAME" \
  "$DATABASE_URL"
```

When `DATABASE_URL` is not available, use explicit environment variables:

```bash
PGPASSWORD="$DB_PASSWORD" pg_dump \
  --host="$DB_HOST" \
  --port="${DB_PORT:-5432}" \
  --username="$DB_USERNAME" \
  --dbname="$DB_DATABASE" \
  --format=custom \
  --verbose \
  --no-owner \
  --no-acl \
  --file="$BACKUP_DIR/$BACKUP_NAME"
```

Minimum checks after backup:

```bash
test -s "$BACKUP_DIR/$BACKUP_NAME"
pg_restore --list "$BACKUP_DIR/$BACKUP_NAME" >/tmp/dz_saas_restore_list.txt
sha256sum "$BACKUP_DIR/$BACKUP_NAME" > "$BACKUP_DIR/$BACKUP_NAME.sha256"
```

## Object Storage Backup

Production should use managed S3-compatible storage with versioning and lifecycle rules where possible.

Minimum policy:

- Separate public assets from private payment/support files where practical.
- Enable bucket versioning where the provider supports it.
- Enable server-side encryption.
- Restrict backup access with least-privilege credentials.
- Keep tenant paths separated.
- Replicate or copy critical buckets to a separate backup location.

Example copy command with AWS CLI:

```bash
aws s3 sync \
  "s3://$AWS_BUCKET" \
  "s3://$BACKUP_BUCKET/dz-saas-commerce/$(date -u +%Y%m%dT%H%M%SZ)/" \
  --only-show-errors
```

For S3-compatible providers that require an endpoint:

```bash
aws --endpoint-url "$AWS_ENDPOINT" s3 sync \
  "s3://$AWS_BUCKET" \
  "s3://$BACKUP_BUCKET/dz-saas-commerce/$(date -u +%Y%m%dT%H%M%SZ)/" \
  --only-show-errors
```

Local MinIO in `docker-compose.yml` is for development only and is not a production backup system.

## Automation Examples

The repository includes deployable examples, not active production configuration:

### Generic PostgreSQL Backup (VM-style host with local PostgreSQL tools)

- `deploy/backup/bin/postgres-backup.sh.example`
- `deploy/backup/systemd/dz-saas-commerce-postgres-backup.service.example`
- `deploy/backup/systemd/dz-saas-commerce-postgres-backup.timer.example`

### Docker Compose Staging PostgreSQL Backup (VPS deployment with Compose)

For VPS deployments where PostgreSQL tools are inside the Docker Compose postgres service:

- `deploy/backup/bin/staging-postgres-backup.sh.example`
- `deploy/backup/systemd/mayfair-staging-postgres-backup.service.example`
- `deploy/backup/systemd/mayfair-staging-postgres-backup.timer.example`

### Object Storage Backup

- `deploy/backup/bin/object-storage-sync.sh.example`
- `deploy/backup/systemd/dz-saas-commerce-object-storage-backup.service.example`
- `deploy/backup/systemd/dz-saas-commerce-object-storage-backup.timer.example`

### Staging Restore Drill

- `deploy/backup/bin/staging-restore-drill.sh.example`
- `deploy/backup/backup.env.example`

## Restore Drill To Temporary Staging Database

**This procedure creates an isolated, temporary database for testing restore procedures. The live staging application database is not overwritten.**

Use `deploy/backup/bin/staging-restore-drill.sh.example`. It enforces:

- Multi-layered validation to prevent production overwrites
- Naming convention enforcement (`dz_saas_restore_drill_*`)
- Admin-only database creation/teardown
- Explicit cleanup confirmation

Required environment variables:

- **`ALLOW_STAGING_RESTORE`**: Must be `true`.
- **`STAGING_ADMIN_DATABASE_URL`**: Administrative connection used only to CREATE and DROP the temporary drill database.
- **`RESTORE_DRILL_DATABASE`**: Temporary database name, must start with `dz_saas_restore_drill_`.
- **`RESTORE_DRILL_DATABASE_URL`**: Full connection URL to the temporary database.
- **`BACKUP_FILE`**: Path to a PostgreSQL custom-format `.dump` backup file.

Cleanup requires dual confirmation:

- **`CLEANUP_RESTORE_DRILL_DATABASE`**: Set to `true`.
- **`CONFIRM_DROP_RESTORE_DRILL_DATABASE`**: Must exactly match `RESTORE_DRILL_DATABASE`.

Example execution:

```bash
source /etc/dz-saas-commerce/backup.env
export ALLOW_STAGING_RESTORE=true
export RESTORE_DRILL_DATABASE="dz_saas_restore_drill_$(date -u +%Y%m%d_%H%M%S)"
export RESTORE_DRILL_DATABASE_URL="postgres://USER:PASSWORD@HOST:5432/${RESTORE_DRILL_DATABASE}"

bash deploy/backup/bin/staging-restore-drill.sh.example
```

After restore, verify with read-only queries:

```bash
psql "$RESTORE_DRILL_DATABASE_URL" -c "SELECT COUNT(*) as users FROM users;"
psql "$RESTORE_DRILL_DATABASE_URL" -c "SELECT COUNT(*) as tenants FROM tenants;"
psql "$RESTORE_DRILL_DATABASE_URL" -c "SELECT COUNT(*) as stores FROM stores;"
psql "$RESTORE_DRILL_DATABASE_URL" -c "SELECT COUNT(*) as products FROM products;"
psql "$RESTORE_DRILL_DATABASE_URL" -c "SELECT COUNT(*) as orders FROM orders;"
```

Record the result in `docs/templates/BACKUP_RESTORE_DRILL_EVIDENCE_TEMPLATE.md` (copy to a new file under `docs/evidence/`).

Cleanup:

```bash
export CLEANUP_RESTORE_DRILL_DATABASE=true
export CONFIRM_DROP_RESTORE_DRILL_DATABASE="$RESTORE_DRILL_DATABASE"
bash deploy/backup/bin/staging-restore-drill.sh.example
```

## Restore Drill Checklist

Run before beta and on a recurring schedule (monthly or before major changes).

1. Select the latest production-like backup.
2. Verify checksum exists and matches.
3. Restore PostgreSQL into an isolated staging database.
4. Restore or sync object storage into a staging-only bucket.
5. Configure staging `.env` with non-production secrets.
6. Run `php artisan optimize:clear`.
7. Run `php artisan migrate:status`.
8. Run `php artisan system:health --scope=ready --format=json`.
9. Run a storefront smoke check against staging.
10. Verify one tenant, one store, one product image, one order, and one invoice can be read.
11. Verify no production email/SMS/payment/shipping integrations are live in staging.
12. Record RPO and RTO observed.
13. Copy `docs/templates/BACKUP_RESTORE_DRILL_EVIDENCE_TEMPLATE.md` to `docs/evidence/BACKUP_RESTORE_DRILL_PROOF_{DATE}.md` and fill it in.

## Evidence Archive

Past drill records are in `docs/evidence/`:

- `docs/evidence/BACKUP_RESTORE_DRILL_PROOF_2026-05-28.md` — first staging restore drill (PASS)
- `docs/evidence/STAGING_POSTGRES_BACKUP_AUTOMATION_PROOF_2026-05-28.md` — backup automation installation (PASS)

## Pre-Migration Backup Gate

Before production migrations:

1. Confirm a successful database backup exists.
2. Confirm object storage backup/replication is healthy when migrations touch file metadata.
3. Confirm rollback limits for destructive migrations.
4. Run `php artisan migrate:status`.
5. Run readiness before and after migration.

## Retention Direction

Initial conservative target:

- Daily database backups for 30 days.
- Weekly database backups for 12 weeks.
- Monthly database backups for 12 months.
- Object storage versioning/lifecycle policy aligned with business retention.

These values must be reviewed against legal, cost, and customer support requirements before production.

## Failure Handling

If backup fails:

1. Mark deployment freeze until backup health is restored.
2. Alert the operator/channel responsible for production.
3. Capture error logs and provider status.
4. Retry once after fixing the cause.
5. Do not run destructive migrations while backup status is unknown.

## Definition Of Done

The backup/restore phase is complete only when:

- Automated database backups are deployed and monitored in production.
- Object storage backup/replication is deployed and monitored.
- Backup artifacts are encrypted or stored with provider-side encryption.
- A restore drill has been executed at least once and recorded.
- Readiness checks pass after restore.
- The observed RTO/RPO are documented.
- Backup monitoring alerts are configured and tested.
