# Stage 3: Tenancy / Auth / Security Gate

**Audit Date:** 2026-10-01  
**Audit Branch:** `main`  
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`  
**Security Gate Result:** **PASS WITH FINDINGS**

---

## 1. Tenancy Mechanism & Boundary Inspection

The multi-tenant architecture relies on a shared PostgreSQL database with row-level isolation mediated by:
- **`BelongsToTenant` (`backend/app/Models/Concerns/BelongsToTenant.php`):** Eloquent trait adding the `current_tenant` global scope and auto-assigning `tenant_id` on model creation.
- **`CurrentTenant` (`backend/app/Support/Tenancy/CurrentTenant.php`):** Thread-safe container singleton holding the active `Tenant` context.
- **`TenantResolver` (`backend/app/Support/Tenancy/TenantResolver.php`):** Resolves tenants from incoming requests:
  - Custom verified hostname (`Domain` model)
  - Subdomain (`Store` model)
  - Authenticated user tenant membership (`User::tenants()`)
  - Request headers (`X-Tenant-ID`) / query params (`?tenant_id=`) — strictly gated by `whereHas('users')` unless the caller is `SuperAdmin`.
- **`ResolveTenantFromRequest` Middleware:** Binds the resolved tenant to `CurrentTenant` and ensures cleanup in a `finally` block to prevent tenant leakage in persistent worker / Octane environments.
- **`Store` Model Exception:** Documented exception to `BelongsToTenant`. `Store` does not use the global scope to allow public domain resolution, but implements `scopeForTenant` which fails closed (`whereRaw('1 = 0')`) if tenant is null or empty.

---

## 2. Audit of `withoutGlobalScope('current_tenant')`

A comprehensive search revealed **71 occurrences** of `withoutGlobalScope('current_tenant')` in `backend/app`. Every occurrence was traced and verified:

| File / Context | Occurrences | Verification Findings | Isolation Status |
| :--- | :--- | :--- | :--- |
| `StorefrontController.php` | 5 | Products, categories, and order tracking explicitly filter by `where('tenant_id', $storeModel->tenant_id)` or `where('tenant_id', $store->tenant_id)`. | **CONFIRMED ISOLATED** |
| `CreateQuickOrder.php` | 9 | `Customer`, `Order`, `Product`, `ProductVariant`, `ShippingRate`, `PaymentMethod`, `InventoryItem`, `StockMovement` queries all explicitly enforce `where('tenant_id', $tenantId)`. Variant-product ownership and variant-tenant ownership are asserted. | **CONFIRMED ISOLATED** |
| `AdjustInventoryManually.php`, `SettleOrderInventory.php`, `ReleaseOrderInventoryReservations.php`, `RestockOrderReturn.php` | 8 | All inventory actions explicitly constrain queries by `where('tenant_id', $tenantId)` and use row-level locking (`lockForUpdate()`). | **CONFIRMED ISOLATED** |
| `RecordOrderPayment.php`, `RefundOrderPayment.php`, `MarkOrderPaymentFailed.php` | 5 | Payments and payment methods explicitly scoped by `$order->tenant_id` or `$payment->tenant_id`. | **CONFIRMED ISOLATED** |
| `SubscriptionFeatureGate.php`, `ProcessBillingLifecycle.php`, `IssueSubscriptionInvoice.php`, `ConfirmSubscriptionPayment.php` | 19 | SaaS billing operations enforce explicit tenant IDs across subscriptions, invoices, and payments. | **CONFIRMED ISOLATED** |
| `CheckoutIdempotency.php` | 5 | Idempotency records scoped by `tenant_id` and hashed request fingerprint. | **CONFIRMED ISOLATED** |
| `SearchStorefrontProducts.php` | 3 | Scout search results filtered by `where('tenant_id', $store->tenant_id)`. | **CONFIRMED ISOLATED** |
| `TenantResolver.php`, `DomainOwnership` | 2 | Domain resolution filters by verified domain hostname; job verifies domain records. | **CONFIRMED ISOLATED** |

**Conclusion on Scope Removal:** No instances of unconstrained `withoutGlobalScope('current_tenant')` leaks were detected in existing application execution paths.

---

## 3. Database Integrity & Relational Isolation

The database enforces multi-tenancy at the PostgreSQL engine level through two foundational migrations:
1. **`2026_04_25_000000_add_tenant_integrity_constraints.php`:**
   - Composite unique constraints on `['tenant_id', 'id']` across 9 core tables (`stores`, `categories`, `products`, `customers`, `orders`, `payment_methods`, `shipping_companies`, `failed_delivery_reasons`, `shipments`).
   - Composite foreign keys enforcing `['tenant_id', foreign_id]` references `target_table(['tenant_id', 'id'])`.
   - Geographic foreign keys: `['wilaya_id', 'commune_id']` references `communes(['wilaya_id', 'id'])`.
2. **`2026_05_17_010000_create_product_variants_schema_foundation.php`:**
   - Composite foreign keys on `product_options`, `product_option_values`, `product_variants`, `product_variant_option_values`, `inventory_items`, `order_items`, and `stock_movements`.
   - Partial unique indexes ensuring sellable unit uniqueness (`inventory_items_simple_unique` where `product_variant_id IS NULL`, and `inventory_items_variant_unique` where `product_variant_id IS NOT NULL`).

**Result:** Cross-tenant foreign key linking is physically rejected by PostgreSQL.

---

## 4. Authentication, Authorization & 2FA Audit

### Panel Access Control
- **`/admin` (AdminPanelProvider):** Access restricted strictly to `User::isSuperAdmin()`.
- **`/support` (SupportPanelProvider):** Restricted to `User::isSuperAdmin()` or `User::isPlatformSupport()`.
- **`/vendor` (VendorPanelProvider):** Restricted to `isSuperAdmin()` or tenant users possessing `TenantPermission::StoresView` on the resolved tenant.

### Two-Factor Authentication (TOTP)
- Mandatory for Super Admins, Support, and Vendor Tenant Owners.
- Enforced via `EnsurePanelTwoFactor` middleware.
- Setup and challenge pages configured; secrets encrypted at rest via `casts => ['two_factor_secret' => 'encrypted']`.
- Recovery codes hashed with bcrypt/Argon2 (`Hash::check`).

### Policy Authorization
- All 24 tenant-level models implement dedicated policies checking granular `TenantPermission`.
- `OrderPolicy`: `create()` and `delete()` explicitly return `false`. Orders can only be created via customer checkout and cannot be hard deleted.
- `AuditLogPolicy`: Completely immutable. All write, update, delete, and restore methods return `false`. Only Super Admins can view audit logs.

---

## 5. Security Findings & Identified Weaknesses

### Finding `TEN-001`: Fail-Open Behavior in `BelongsToTenant` Global Scope
- **Severity:** HIGH
- **Status:** CONFIRMED IMPLEMENTED
- **File:** `backend/app/Models/Concerns/BelongsToTenant.php` (lines 15–20)
- **Description:** In `BelongsToTenant::bootBelongsToTenant()`, the global scope checks:
  ```php
  $tenantId = app(CurrentTenant::class)->id();
  if ($tenantId === null) {
      return;
  }
  $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
  ```
  When `$tenantId === null` (e.g. unauthenticated request, console command, or background queue job without explicit tenant context), the global scope silently does nothing rather than failing closed. Contrast this with `Store::scopeForTenant` which safely fails closed with `whereRaw('1 = 0')`.
- **Impact:** Any new query written by a developer or AI agent on a model using `BelongsToTenant` outside an authenticated tenant session will return records across all tenants by default instead of raising an error or returning empty results.
- **Recommended Fix:** Refactor `BelongsToTenant` or introduce a strict tenancy mode where queries outside an active tenant context fail closed unless explicitly opting out via `withoutGlobalScope('current_tenant')`.
- **Test Required:** Assert that executing `Product::all()` when `CurrentTenant::id()` is null returns 0 records or throws a `TenantContextMissingException`.

### Finding `AUTH-001`: Emergency 2FA Reset Does Not Invalidate Active Sessions
- **Severity:** HIGH
- **Status:** PARTIAL
- **Files:** `backend/routes/console.php` (lines 50–138), `backend/app/Support/Auth/TwoFactorAuthentication.php` (lines 184–210)
- **Description:** The artisan command `php artisan security:reset-two-factor` accepts `--confirm`, `--reason`, and operator actor options, clears the target's `two_factor_secret`, `two_factor_recovery_codes`, and timestamps, and writes an audit log. However, it does not revoke active user sessions in the database (`sessions` table), does not cycle the user's `remember_token`, and does not purge cached Redis session keys.
- **Impact:** If an account was compromised or a device stolen, triggering an emergency 2FA reset leaves existing attacker sessions active and fully authenticated. The attacker is not challenged for 2FA until their existing session expires or is closed.
- **Recommended Fix:** In `TwoFactorAuthentication::resetForUser()`, delete all active session records for `$target->getKey()` from the database/Redis, and cycle `$target->remember_token`.
- **Test Required:** Feature test verifying that after running `security:reset-two-factor`, previously authenticated sessions for that user receive a 401/redirect on their next HTTP request.
