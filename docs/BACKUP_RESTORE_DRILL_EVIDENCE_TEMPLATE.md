# Backup Restore Drill Evidence Template

Use this after a real restore drill. This is an evidence template, not proof that a drill has already happened.

## Safety Checklist

- [ ] Restore target is a separate database.
- [ ] Restore target is not production.
- [ ] Object storage restore uses a staging-only bucket or prefix.
- [ ] No production secrets are copied into this evidence.
- [ ] No raw customer phone numbers, addresses, private file URLs, tokens, or payment proof URLs are recorded.
- [ ] Any failed step creates an issue or task with owner and due date.

## Evidence

| Field | Value |
|---|---|
| Date/time UTC |  |
| Operator |  |
| Commit SHA |  |
| Environment |  |
| Backup source |  |
| Restore target |  |
| Database backup file |  |
| Object storage backup path |  |
| Backup duration |  |
| Restore duration |  |
| RPO observed |  |
| RTO observed |  |
| Tenants count |  |
| Stores count |  |
| Products count |  |
| Orders count |  |
| Sample media/file validation |  |
| Backend live result |  |
| Backend ready result |  |
| Storefront smoke result |  |
| Failed jobs count |  |
| Issues found |  |
| Follow-up tasks |  |

## Checks Run

Record commands and results without secrets:

```bash
pg_restore --list <backup-file>
cd backend
php artisan optimize:clear
php artisan migrate:status
php artisan system:health --scope=live --format=json
php artisan system:health --scope=ready --format=json
php artisan queue:failed
```

Storefront/proxy smoke:

```bash
curl -fsSIL --connect-timeout 5 --max-time 20 <staging-storefront-url>
curl -fsS --connect-timeout 5 --max-time 20 <staging-backend-url>/api/system/health/live
curl -fsS --connect-timeout 5 --max-time 20 <staging-backend-url>/api/system/health/ready
```

## Notes

- Attach sanitized logs or workflow links only.
- Keep backup paths specific enough for traceability, but do not include signed/private URLs.
- Record observed RPO/RTO from the actual run, not targets.
- If data counts differ from expected values, document why before calling the drill successful.
