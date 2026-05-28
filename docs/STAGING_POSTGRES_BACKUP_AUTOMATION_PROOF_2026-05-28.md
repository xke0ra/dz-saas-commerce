# Staging PostgreSQL Backup Automation Proof - 2026-05-28

This document records sanitized proof that staging PostgreSQL backup automation was installed, smoke-tested, and scheduled for `dz-saas-commerce` / Mayfair.

It does not include database dumps, secrets, credentials, or raw application data.

## Scope

- Environment: staging
- VPS project path: `/opt/mayfair`
- Backup target: PostgreSQL staging database
- Backup mechanism: Docker Compose based `pg_dump` through the `postgres` service
- Systemd timer: `mayfair-staging-postgres-backup.timer`

## Installation

Installed on the VPS:

- Backup script: `/opt/mayfair/deploy/backup/bin/staging-postgres-backup.sh`
- Environment file: `/etc/mayfair/backup.env`
- Backup directory: `/secure/backups/dz-saas-commerce/postgres`
- Systemd service: `mayfair-staging-postgres-backup.service`
- Systemd timer: `mayfair-staging-postgres-backup.timer`

## Manual script smoke

Manual execution of the installed backup script completed successfully.

Created backup:

- `dz_saas_commerce_20260528T204417Z.dump`
- Restore list generated: yes
- SHA-256 checksum generated: yes
- Checksum verification: `OK`

## Systemd service smoke

Manual execution through systemd completed successfully.

Created backup:

- `dz_saas_commerce_20260528T204606Z.dump`
- Restore list generated: yes
- SHA-256 checksum generated: yes
- Checksum verification: `OK`

## Timer

The systemd timer was enabled and active.

Observed next scheduled run:

- `Fri 2026-05-29 01:16:53 UTC`

## Result

PASS.

Staging PostgreSQL backup automation is installed, smoke-tested, and scheduled.

## Remaining backup/restore work

Still pending before production readiness:

- Backup failure alerting.
- Backup age monitoring.
- Object storage backup/replication proof.
- Off-host/off-provider backup retention.
- Recurring restore drill cadence.
