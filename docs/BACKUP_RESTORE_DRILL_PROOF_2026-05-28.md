# Backup Restore Drill Proof - 2026-05-28

This document records a completed staging PostgreSQL backup/restore drill for `dz-saas-commerce`.

It is a sanitized proof record. It does not include database dumps, secrets, credentials, or raw application data.

## Scope

- Environment: staging
- Target project path on VPS: `/opt/mayfair`
- Database: PostgreSQL staging database
- Restore target: isolated temporary restore drill database
- Live staging application database overwritten: no

## Backup

- Backup type: PostgreSQL custom-format dump
- Backup filename: `dz_saas_commerce_20260528T154552Z.dump`
- Backup size: `252K`
- Restore list generated: yes
- SHA-256 checksum generated: yes
- Checksum verification result: `OK`

## Restore

- Temporary restore database: `dz_saas_restore_drill_20260528_154552`
- Restore method: PostgreSQL 17 `pg_restore`
- Restore target type: isolated temporary database inside the staging PostgreSQL container
- Restore result: success

## Verification

Read-only verification queries were executed against the temporary restore database.

Results:

- users_count: 1
- tenants_count: 1
- stores_count: 1
- products_count: 4
- orders_count: 3
- active_stores_count: 1

## Cleanup

- Temporary restore database dropped: yes
- Drop result: `DROP DATABASE`
- Post-cleanup verification: no database row returned for `dz_saas_restore_drill_20260528_154552`

## Final status

PASS.

Backup creation, checksum verification, restore, read-only verification, evidence recording, and cleanup completed successfully.

## Remaining backup/restore work

This proof records one manual staging restore drill.

Still pending before production readiness:

- Automated backup schedule deployment.
- Backup monitoring and alerting.
- Object storage backup/replication proof.
- Recurring restore drill cadence.
- Off-host/off-provider backup retention policy.
