# Database Migration Runbook

Last updated: 2026-05-31

This runbook defines the procedure for running database migrations safely in staging and production for `dz-saas-commerce`.

> **Critical rule:** Never run destructive migrations in production without a confirmed recent backup. See `docs/BACKUP_RESTORE_RUNBOOK.md`.

---

## Migration Philosophy

- Migrations are run **manually by an operator** before deploying new application code. They are not run automatically as a Docker entrypoint side effect.
- Prefer additive, backward-compatible migrations. New columns should be nullable or have a default value so the old application code can still run while the migration is applied.
- Destructive changes (drop column, rename column, change type) require a multi-step migration plan.
- Financial and tenant-integrity constraints (`CHECK`, composite FK) must never be removed without an ADR.

---

## Pre-Migration Checklist

Complete all steps before running any migration in staging or production:

```bash
# 1. Confirm a recent backup exists
# Check docs/evidence/ for last backup proof
# Or inspect backup storage directly

# 2. Check migration status
cd backend && php artisan migrate:status

# 3. Review all pending migrations
php artisan migrate:status | grep Pending

# 4. Read each pending migration file before running
# Pay special attention to:
#   - DROP COLUMN, RENAME COLUMN, CHANGE type
#   - Removal of indexes or constraints
#   - Large table operations (may lock rows)

# 5. Check current readiness
php artisan system:health --scope=ready --format=json
```

---

## Running Migrations — Staging

```bash
# On the staging host
cd /srv/dz-saas-commerce/backend

# Run with --force (required in non-local environments)
php artisan migrate --force

# Verify
php artisan migrate:status
php artisan system:health --scope=ready --format=json
```

If a migration fails:

```bash
# Check what ran
php artisan migrate:status

# Check error logs
docker logs backend --since 5m

# Do NOT retry blindly — inspect the migration file and fix the root cause first
```

---

## Running Migrations — Production

**Never run migrations in production without completing the pre-migration checklist above.**

```bash
# 1. Notify the team: "[MIGRATION] Starting migration {migration_name} on production — {time UTC}"

# 2. Put the application in maintenance mode (optional, for long migrations)
php artisan down --retry=60 --secret="your-bypass-secret"

# 3. Run the migration
php artisan migrate --force

# 4. Verify
php artisan migrate:status
php artisan system:health --scope=ready --format=json

# 5. Bring the application back up (if you put it in maintenance mode)
php artisan up

# 6. Quick smoke check
curl -fsS https://api.mayfairs.app/api/system/health/ready
curl -fsS https://mayfairs.app

# 7. Notify the team: "[MIGRATION COMPLETE] {migration_name} — {time UTC}"
```

---

## Safe Migration Patterns

### Adding a nullable column

Always safe — old code ignores the column; new code reads it.

```php
$table->string('new_field')->nullable();
```

### Adding a column with a default

Safe if the default is a constant expression (not a subquery).

```php
$table->boolean('is_active')->default(true);
```

### Adding an index

Safe. Runs quickly on small tables; may lock large tables briefly. Use `CONCURRENTLY` via raw SQL for large tables when the database supports it.

```php
$table->index(['tenant_id', 'status', 'created_at']);
```

### Dropping a column — Two-Step Approach

**Do not drop in a single migration with a code change.** Use two deployments:

1. **Deploy 1:** Remove all references to the column in code. Leave the column in the database.
2. **Deploy 2:** Run a migration that drops the column.

This ensures the old code never encounters a missing column during a partial deploy.

### Renaming a column — Three-Step Approach

1. **Deploy 1:** Add new column with new name. Copy data in migration. Write to both columns in code.
2. **Deploy 2:** Remove reads from old column. Keep writing to both.
3. **Deploy 3:** Drop old column.

---

## Large Table Migrations

For tables with millions of rows, standard `ALTER TABLE` may lock for extended periods. For the current scale (pre-beta), this is not yet a concern. When it becomes one:

- Use `ALTER TABLE ... ADD COLUMN ... DEFAULT NULL` (does not rewrite the table in PostgreSQL ≥ 11).
- Run data backfills as separate batched commands, not inline in the migration.
- Consider using a tool like `pg_repack` for online index rebuilds on very large tables.

---

## Tenant-Integrity Constraints

The project uses composite foreign keys for cross-tenant referential integrity (see `2026_04_25_000000_add_tenant_integrity_constraints.php`). Examples:

```sql
orders (tenant_id, store_id) → stores (tenant_id, id)
payments (tenant_id, order_id) → orders (tenant_id, id)
```

**Never remove these constraints without an ADR.** They are a security control, not just a data integrity concern. If you need to migrate data that temporarily violates a constraint, use `DEFERRABLE INITIALLY DEFERRED` in a transaction, not a constraint drop.

---

## Financial CHECK Constraints

Migrations must not remove financial CHECK constraints:

```sql
CHECK (total_minor >= 0)
CHECK (total_minor = subtotal_minor + tax_minor)
CHECK (paid_amount_minor <= total_minor)
```

These enforce correctness that the application layer also enforces. Both layers are required.

---

## Migration Rollback

See `docs/ROLLBACK_RUNBOOK.md` — Database Migration Rollback section.

Short version:
- Only additive migrations can be rolled back cleanly with `php artisan migrate:rollback`.
- Destructive migrations that have run require a database restore.
- Always check that the `down()` method is implemented before relying on rollback.

---

## Post-Migration Verification

After any migration in staging or production:

```bash
# 1. Migration status
php artisan migrate:status                        # no Pending rows

# 2. Readiness check
php artisan system:health --scope=ready --format=json   # all checks green

# 3. Spot check affected tables
cd backend && php artisan tinker
# >>> Schema::getColumnListing('affected_table')
# >>> DB::table('affected_table')->count()

# 4. Run relevant tests
php artisan test --filter=AffectedDomain

# 5. Quick storefront smoke if the migration affects checkout, products, or orders
curl -fsS https://mayfairs.app
```

---

## Definition Of Done

A migration is complete when:

1. `php artisan migrate:status` shows it as `Ran`.
2. `php artisan system:health --scope=ready` passes.
3. Relevant tests pass.
4. No new errors in application logs since migration.
5. `CHANGELOG.md` has a migration entry if the change is significant.
