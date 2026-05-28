# Backup And Restore Runbook

Last updated: 2026-05-07

This runbook defines the first operational backup and restore contract for `dz-saas-commerce`. It is a documented procedure, not proof that a restore drill has already been executed.

## Current Status

Implemented:

- PostgreSQL backup and restore procedure is documented.
- S3-compatible object storage backup direction is documented.
- Restore drill checklist is documented.
- Pre-migration backup requirement is linked to production readiness.
- Example PostgreSQL backup script exists: `deploy/backup/bin/postgres-backup.sh.example`.
- Example object storage backup sync script exists: `deploy/backup/bin/object-storage-sync.sh.example`.
- Example staging restore drill script exists with explicit safeguards: `deploy/backup/bin/staging-restore-drill.sh.example`.
- Example systemd backup services/timers exist under `deploy/backup/systemd/`.
- Example backup environment template exists: `deploy/backup/backup.env.example`.

Not yet proven:

- Automated production backup schedule deployment.
- Managed PostgreSQL point-in-time recovery configuration.
- Object storage replication/lifecycle policy.
- Encrypted offsite backup storage.
- Staging restore drill execution.
- Backup monitoring and failed-backup alerting.

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

- `deploy/backup/bin/postgres-backup.sh.example`
- `deploy/backup/bin/object-storage-sync.sh.example`
- `deploy/backup/bin/staging-restore-drill.sh.example`
- `deploy/backup/backup.env.example`
- `deploy/backup/systemd/dz-saas-commerce-postgres-backup.service.example`
- `deploy/backup/systemd/dz-saas-commerce-postgres-backup.timer.example`
- `deploy/backup/systemd/dz-saas-commerce-object-storage-backup.service.example`
- `deploy/backup/systemd/dz-saas-commerce-object-storage-backup.timer.example`

Operator installation shape for a VM-style deployment:

```bash
sudo install -d -m 0750 -o root -g www-data /etc/dz-saas-commerce
sudo install -m 0640 -o root -g www-data deploy/backup/backup.env.example /etc/dz-saas-commerce/backup.env
sudo install -d -m 0755 /opt/dz-saas-commerce/deploy/backup/bin
sudo install -m 0755 deploy/backup/bin/postgres-backup.sh.example /opt/dz-saas-commerce/deploy/backup/bin/postgres-backup.sh
sudo install -m 0755 deploy/backup/bin/object-storage-sync.sh.example /opt/dz-saas-commerce/deploy/backup/bin/object-storage-sync.sh
sudo install -m 0755 deploy/backup/bin/staging-restore-drill.sh.example /opt/dz-saas-commerce/deploy/backup/bin/staging-restore-drill.sh
```

Then fill `/etc/dz-saas-commerce/backup.env` from the secret manager. Do not store real secrets in the repository.

Systemd example installation:

```bash
sudo cp deploy/backup/systemd/dz-saas-commerce-postgres-backup.service.example /etc/systemd/system/dz-saas-commerce-postgres-backup.service
sudo cp deploy/backup/systemd/dz-saas-commerce-postgres-backup.timer.example /etc/systemd/system/dz-saas-commerce-postgres-backup.timer
sudo cp deploy/backup/systemd/dz-saas-commerce-object-storage-backup.service.example /etc/systemd/system/dz-saas-commerce-object-storage-backup.service
sudo cp deploy/backup/systemd/dz-saas-commerce-object-storage-backup.timer.example /etc/systemd/system/dz-saas-commerce-object-storage-backup.timer
sudo systemctl daemon-reload
sudo systemctl enable --now dz-saas-commerce-postgres-backup.timer
sudo systemctl enable --now dz-saas-commerce-object-storage-backup.timer
```

Manual smoke before enabling timers:

```bash
sudo systemctl start dz-saas-commerce-postgres-backup.service
sudo systemctl start dz-saas-commerce-object-storage-backup.service
sudo systemctl list-timers 'dz-saas-commerce-*backup*'
```

The database script writes:

- `.dump` PostgreSQL custom-format backup.
- `.dump.list` restore listing generated by `pg_restore --list`.
- `.dump.sha256` checksum.

The restore drill script refuses to run unless `ALLOW_STAGING_RESTORE=true` and the target database URL does not appear to reference production.

## Restore Drill To Temporary Staging Database

**This procedure creates an isolated, temporary database for testing restore procedures. The live staging application database is not overwritten.**

Use the provided `deploy/backup/bin/staging-restore-drill.sh.example` script to execute restore drills. It enforces all safety requirements:

- Multi-layered validation to prevent production overwrites
- Naming convention enforcement (`dz_saas_restore_drill_*`)
- Admin-only database creation/teardown
- Explicit cleanup confirmation

Required environment variables:

- **`ALLOW_STAGING_RESTORE`**: Must be set to `true` to enable any restore attempt.
- **`STAGING_ADMIN_DATABASE_URL`**: Administrative connection (e.g., `postgres://user:pass@host:5432/postgres`) used only to CREATE and DROP the temporary drill database. Must not reference production.
- **`RESTORE_DRILL_DATABASE`**: Temporary database name, enforced to start with `dz_saas_restore_drill_` (e.g., `dz_saas_restore_drill_20260528_120000`). This naming convention prevents accidental overwrites of live databases.
- **`RESTORE_DRILL_DATABASE_URL`**: Full connection URL to the temporary database (e.g., `postgres://user:pass@host:5432/dz_saas_restore_drill_20260528_120000`).
- **`BACKUP_FILE`**: Path to a PostgreSQL custom-format `.dump` backup file to restore.

Cleanup is disabled by default and requires explicit dual confirmation:

- **`CLEANUP_RESTORE_DRILL_DATABASE`**: Set to `true` to enable cleanup mode.
- **`CONFIRM_DROP_RESTORE_DRILL_DATABASE`**: Must be set to the exact value of `RESTORE_DRILL_DATABASE`. If the strings do not match exactly, cleanup is refused.

Example execution:

```bash
source /etc/dz-saas-commerce/backup.env
export ALLOW_STAGING_RESTORE=true
export RESTORE_DRILL_DATABASE="dz_saas_restore_drill_$(date -u +%Y%m%d_%H%M%S)"
export RESTORE_DRILL_DATABASE_URL="postgres://USER:PASSWORD@HOST:5432/${RESTORE_DRILL_DATABASE}"

bash deploy/backup/bin/staging-restore-drill.sh.example
```

After successful database restore, verify the drill database using read-only `psql` queries against `RESTORE_DRILL_DATABASE_URL`:

```bash
# Extract database credentials from RESTORE_DRILL_DATABASE_URL if necessary
# Example: postgres://user:password@host:5432/dz_saas_restore_drill_20260528_120000

psql "$RESTORE_DRILL_DATABASE_URL" -c "SELECT COUNT(*) as user_count FROM users;"
psql "$RESTORE_DRILL_DATABASE_URL" -c "SELECT COUNT(*) as tenant_count FROM tenants;"
psql "$RESTORE_DRILL_DATABASE_URL" -c "SELECT COUNT(*) as store_count FROM stores;"
psql "$RESTORE_DRILL_DATABASE_URL" -c "SELECT COUNT(*) as product_count FROM products;"
psql "$RESTORE_DRILL_DATABASE_URL" -c "SELECT COUNT(*) as order_count FROM orders;"
```

If using restored object storage data, point the temporary drill environment to a staging-only bucket copy, not the production bucket.

To test application-level verification (optional), temporarily point the backend `DATABASE_URL` to the drill database in a non-production environment, then run:

```bash
cd backend
php artisan migrate:status
php artisan system:health --scope=ready --format=json
php artisan route:list
```

Do not run these commands against the live staging database. Always verify drill database connectivity before running application commands.

To clean up the temporary database after verification:

```bash
export CLEANUP_RESTORE_DRILL_DATABASE=true
export CONFIRM_DROP_RESTORE_DRILL_DATABASE="$RESTORE_DRILL_DATABASE"

bash deploy/backup/bin/staging-restore-drill.sh.example
```

The script will drop the temporary database and exit. The dual-confirmation requirement (`CLEANUP_RESTORE_DRILL_DATABASE=true` AND exact database name match) ensures accidental cleanup is impossible.

## Restore Drill Checklist

Run this before beta and then on a recurring operational schedule.

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
12. Record:
    - backup timestamp
    - restore start/end time
    - backup file size
    - checksum result
    - operator
    - issues found
    - RTO observed
    - RPO observed

Use `docs/BACKUP_RESTORE_DRILL_EVIDENCE_TEMPLATE.md` to record the first real drill. The template is intentionally separate so it can be copied into an issue, incident record, or operations log without duplicating this runbook.

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

- Automated database backups are deployed and monitored.
- Object storage backup/replication is deployed and monitored.
- Backup artifacts are encrypted or stored with provider-side encryption.
- A staging restore drill has been executed and recorded.
- Readiness checks pass after restore.
- The observed RTO/RPO are documented.
