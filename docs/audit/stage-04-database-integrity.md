# Stage 4: Database and Integrity Gate

**Audit Date:** 2026-10-01  
**Audit Branch:** `main`  
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`  
**Database Gate Result:** **PASS WITH FINDINGS**

---

## 1. Schema & Migration Architecture

The repository contains **49 database migrations** in `backend/database/migrations/`. The schema architecture is strictly relational and optimized for PostgreSQL.

### Key Structural Patterns
1. **Identifier Strategy:**
   - Platform users use auto-incrementing `bigIncrements('id')`.
   - Algerian administrative geography uses standardized administrative codes (`wilayas.id` as `unsignedSmallInteger` 1–58, `communes.id` as `unsignedInteger` 1–1541).
   - All multi-tenant entities (`tenants`, `stores`, `products`, `orders`, `inventory_items`, `product_variants`, `shipments`, `invoices`, etc.) use ULIDs (`ulid('id')->primary()`), ensuring high-entropy, sortable, non-sequential identifiers.
2. **Monetary Representation:**
   - Every financial column across the schema uses unsigned integers representing minor currency units (centimes for DZD):
     - `products.price_minor`, `compare_at_price_minor`, `cost_price_minor`
     - `product_variants.price_minor`, `compare_at_price_minor`, `cost_price_minor`
     - `shipping_rates.price_minor`
     - `orders.subtotal_minor`, `shipping_fee_minor`, `discount_minor`, `total_minor`
     - `order_items.unit_price_minor`, `total_minor`
     - `payments.amount_minor`, `invoices.total_minor`, `subscription_payments.amount_minor`
   - Floating-point types (`float`, `double`) are completely absent from monetary definitions.
3. **Database Engine-Level Check Constraints:**
   - PostgreSQL check constraints enforce non-negative values for all monetary columns (`CHECK (price_minor >= 0)`).
   - Check constraints enforce positive quantities for order items (`CHECK (quantity > 0)`).
   - Non-blank string constraints enforce data hygiene on option names, option values, variant signatures, and variant SKUs.

---

## 2. Multi-Tenant Integrity & Relational Isolation

The database architecture features enterprise-grade relational tenancy guarantees enforced directly by the PostgreSQL engine:

### Composite Foreign Key Enforcement
Migration `2026_04_25_000000_add_tenant_integrity_constraints.php` establishes composite unique keys on `['tenant_id', 'id']` across all core tables, followed by composite foreign keys:
- `products(['tenant_id', 'category_id'])` -> `categories(['tenant_id', 'id'])`
- `orders(['tenant_id', 'store_id'])` -> `stores(['tenant_id', 'id'])`
- `orders(['tenant_id', 'customer_id'])` -> `customers(['tenant_id', 'id'])`
- `order_items(['tenant_id', 'order_id'])` -> `orders(['tenant_id', 'id'])`
- `order_items(['tenant_id', 'product_id'])` -> `products(['tenant_id', 'id'])`
- `payments(['tenant_id', 'order_id'])` -> `orders(['tenant_id', 'id'])`
- `payments(['tenant_id', 'payment_method_id'])` -> `payment_methods(['tenant_id', 'id'])`
- `shipments(['tenant_id', 'order_id'])` -> `orders(['tenant_id', 'id'])`
- `shipments(['tenant_id', 'shipping_company_id'])` -> `shipping_companies(['tenant_id', 'id'])`
- `order_returns(['tenant_id', 'order_id'])` -> `orders(['tenant_id', 'id'])`

Migration `2026_05_17_010000_create_product_variants_schema_foundation.php` extends this composite isolation to variants and inventory:
- `product_options(['tenant_id', 'product_id'])` -> `products(['tenant_id', 'id'])`
- `product_variants(['tenant_id', 'product_id'])` -> `products(['tenant_id', 'id'])`
- `inventory_items(['tenant_id', 'product_variant_id'])` -> `product_variants(['tenant_id', 'id'])`
- `stock_movements(['tenant_id', 'product_variant_id'])` -> `product_variants(['tenant_id', 'id'])`

**Relational Guarantee:** It is mathematically impossible in PostgreSQL for an order, item, payment, or variant belonging to Tenant A to reference a product or customer belonging to Tenant B.

---

## 3. Inventory & Sellable Unit Integrity

1. **Sellable Unit Uniqueness:**
   Migration `2026_05_18_000000_activate_variant_inventory_uniqueness.php` replaced single-product uniqueness with partial unique indexes:
   ```sql
   CREATE UNIQUE INDEX inventory_items_simple_unique 
     ON inventory_items (tenant_id, product_id) 
     WHERE product_variant_id IS NULL;

   CREATE UNIQUE INDEX inventory_items_variant_unique 
     ON inventory_items (tenant_id, product_variant_id) 
     WHERE product_variant_id IS NOT NULL;
   ```
   This guarantees that simple products have at most one inventory row, and variable products have exactly one inventory row per variant.
2. **Overselling & Reservation Constraints:**
   Migration `2026_04_25_000100_allow_inventory_backorders_to_exceed_stock.php` enforces:
   ```sql
   ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_reserved_not_above_quantity 
     CHECK (allow_backorders OR reserved_quantity <= quantity);
   ```
   Along with `CHECK (quantity >= 0)` and `CHECK (reserved_quantity >= 0)`.

---

## 4. Identified Database Findings

### Finding `DB-001`: Data Mutation in Schema Migration
- **Severity:** LOW
- **Status:** CONFIRMED IMPLEMENTED
- **File:** `backend/database/migrations/2026_04_24_000010_promote_single_existing_user_to_super_admin.php`
- **Description:** Migration directly queries and updates the `users` table via `DB::table('users')`, promoting the single user to `super_admin`. Its `down()` method is empty (`//`).
- **Impact:** Clean fresh installs are unaffected because `users` table is empty when migrations run. However, mixing data mutations in schema migrations is bad practice and non-reversible.
- **Recommended Fix:** Keep as-is for historical migration sequence; ensure future migrations use seeders or console commands for data changes.

### Finding `DB-002`: Irreversible Rollback in Variant Inventory Migration
- **Severity:** MEDIUM
- **Status:** CONFIRMED IMPLEMENTED
- **File:** `backend/database/migrations/2026_05_18_000000_activate_variant_inventory_uniqueness.php` (lines 28–29)
- **Description:** The `down()` method attempts to re-create `UNIQUE (tenant_id, product_id)` on `inventory_items`. If variable products have been created with multiple variant inventory records, rolling back this migration will fail with a unique constraint violation.
- **Impact:** Forward migrations are safe; rollbacks in environments with variant data will crash.
- **Recommended Fix:** Document in `DATABASE_MIGRATION_RUNBOOK.md` that migrations from `2026_05_17_010000` onward are forward-only once variants are seeded.

### Finding `DB-003`: Hard Deletion Restriction on Variants with Historical Orders
- **Severity:** LOW (Intentional Design Guard)
- **Status:** CONFIRMED IMPLEMENTED
- **File:** `backend/database/migrations/2026_05_17_010000_create_product_variants_schema_foundation.php` (line 200)
- **Description:** Foreign key `order_items_variant_fk` references `product_variants(id)` without `ON DELETE SET NULL` or `CASCADE`, defaulting to `RESTRICT`. If an order item exists for a variant, deleting the variant record throws an integrity constraint violation.
- **Impact:** Protects financial history; merchants cannot accidentally hard-delete variants that have sales history. Soft-deletes or status disabling (`status = 'archived'`) should be used instead.
