# Evidence Archive

This folder contains point-in-time proof documents for operational events: staging smokes, restore drills, backup automation proofs, and similar records.

These are **records of past events**, not operating procedures. For procedures, see `docs/BACKUP_RESTORE_RUNBOOK.md`, `docs/MONITORING_ALERTING_RUNBOOK.md`, and other runbooks.

## Files In This Folder

| File | Date | Event | Result |
|------|------|-------|--------|
| `BACKUP_RESTORE_DRILL_PROOF_2026-05-28.md` | 2026-05-28 | Staging PostgreSQL restore drill | ✅ PASS |
| `STAGING_POSTGRES_BACKUP_AUTOMATION_PROOF_2026-05-28.md` | 2026-05-28 | Backup automation installation on `mayfair-vps` | ✅ PASS |
| `STAGING_SMOKE_PROOF_2026-05-26_AR.md` | 2026-05-26 | External staging smoke after 2FA fix (commit `045c264`) | ✅ PASS |

## Adding New Evidence

1. Use the appropriate template from `docs/templates/`.
2. Name the file `{EVENT_TYPE}_PROOF_{YYYY-MM-DD}.md`.
3. Do not put secrets, raw passwords, or customer data in evidence files.
4. Add a row to the table above.
5. Link to this file from the relevant runbook's "Evidence Archive" section.
