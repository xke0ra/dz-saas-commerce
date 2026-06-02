# Domain Contracts — English Summary

Last updated: 2026-05-31

This document is an English summary of [`docs/DOMAIN_CONTRACTS_AR.md`](DOMAIN_CONTRACTS_AR.md), which is the authoritative Arabic version. If there is any conflict, the Arabic version takes precedence. Read the Arabic version in full before making changes to checkout, inventory, billing, or tenancy logic.

---

## 1. Tenancy Contract

- Every tenant-scoped model **must** have `tenant_id`.
- No cross-tenant relationships between commercial data.
- Sensitive relationships require composite foreign keys: `(tenant_id, store_id)`, `(tenant_id, product_id)`.
- Every tenant-owned model must use `BelongsToTenant` or a documented equivalent.
- `Store` is a documented exception — it participates in public store resolution. Any new query on it must be reviewed for tenant isolation.
- Never remove the `current_tenant` global scope without a documented reason.
- **Every `withoutGlobalScope('current_tenant')` must be followed by `->where('tenant_id', $tenantId)`** plus a test when touching checkout, billing, inventory, or tenancy.
- Public storefront cannot rely on the host header alone for sensitive operations; must resolve store/tenant explicitly.

## 2. Checkout Contract

- Storefront sends: `items`, customer data, shipping selection, payment intent.
- **Backend calculates:** prices, discounts, shipping fees, totals, currency, payment status, inventory reservation.
- The frontend must never send `subtotal`, `discount`, `shipping_fee`, or `total`.
- Inventory reservation happens inside a DB transaction with `lockForUpdate()` where needed.
- Quick checkout records a `reserved` stock movement inside the same transaction as order creation.
- Checkout uses `Idempotency-Key` when the client sends it; has a duplicate-window fallback when absent. The fallback is best-effort only.
- Order items store snapshots: product name, SKU, unit price, quantity, line total — and for variants: `product_variant_id`, `variant_title`, `variant_sku`, `selected_options`.
- `ProductType` is the source of truth for `simple` vs `variable`.
- A `simple` product is purchased **without** `product_variant_id`. Checkout must reject it if a variant id is sent.
- A `variable` product **must** be purchased via `product_variant_id`. Checkout must reject checkout on the parent product without a variant.
- Backend always re-validates variant ownership and availability, regardless of what the storefront sends.
- Checkout failures must return safe validation errors without leaking tenant data or internal IDs.

## 3. Product Type and Variants Contract

- `products.type` accepts `simple` or `variable`. All products default to `simple` until explicitly changed.
- Simple products use product-level inventory where `product_variant_id IS NULL`.
- Variable products use variant-level inventory.
- `ProductVariant.effectivePriceMinor()` uses variant price if set; falls back to parent product price.
- Vendor UI must prevent linking an option value to a different product or tenant.
- Product detail API exposes `type`, `variants`, `options` for `variable` products only. Never exposes `tenant_id`, `cost_price_minor`, or internal metadata.

## 4. Inventory Contract

- Inventory is never modified directly from controllers or UI callbacks.
- Any future change to `quantity` or `reserved_quantity` must create a `stock_movement` record.
- `stock_movements` is an operational, append-oriented ledger — not a replacement for `AuditLog`.
- Laravel backend is the only source of truth for stock movements. Storefront writes nothing.
- `ReleaseOrderInventoryReservations` records a `released` stock movement when releasing.
- `SettleOrderInventory` records a `settled` stock movement when settling.
- `RestockOrderReturn` records a `restocked` stock movement on return restock.
- All lifecycle flows look up inventory by sellable unit: `tenant_id + product_id + product_variant_id`, or `product_variant_id IS NULL` for simple products.
- Manual inventory adjustment must go through `AdjustInventoryManually` only. It writes both a `StockMovement` and an `AuditLog`.
- Manual adjustment accepts only `manual_adjustment` or `correction` as movement types, requires `inventory.update` permission, and a non-empty reason.
- `reserved_quantity` = quantity reserved for orders not yet settled.
- `available = quantity - reserved_quantity` when `track_quantity = true`.
- Checkout never deducts `quantity` directly. It reserves first, then settles or releases based on order status.
- Inventory uniqueness: one row per `tenant_id + product_id` (simple) or `tenant_id + product_variant_id` (variable).

## 5. Stock Movement Types

| Type | When Used |
|------|-----------|
| `reserved` | Checkout — order creation |
| `released` | Order cancellation / inventory release |
| `settled` | Order delivery / final settlement |
| `restocked` | Return restock |
| `manual_adjustment` | Deliberate manual correction by staff |
| `correction` | System correction |
| `import` | Bulk import (not yet implemented as a full flow) |
| `return_received` | Return received (not yet implemented as a full flow) |

## 6. Billing Contract

- `plans`, `features`, `subscriptions`, and `usage_counters` define SaaS limits.
- Feature gates must run before restricted operations (product limits, custom domains, staff limits, coupons, analytics).
- Any plan/subscription/payment change must be audited.
- Manual payment proof must record actor, decision, reference, proof metadata, and rejection reason.
- Frontend must never rely on unconfirmed subscription state from client state.

## 7. Storefront API Contract

- API never exposes `tenant_id` to the public unless there is a documented reason.
- Storefront never sends trusted totals or final prices.
- Public field names in responses are stable; they do not change without a migration plan or versioning.
- Product/catalog responses expose only products visible and permitted for that store.
- Product detail response exposes `type`. For `variable` products: active variants only, no `tenant_id`, no `cost_price_minor`, no internal metadata, availability computed from variant-level inventory.
- Checkout response contains a safe order confirmation — no raw operational internal data.

## 8. Store Readiness Contract

- `StoreReadinessChecker` is the domain gate for publishing. It is not a production deployment gate.
- Result is structured: `ready`, `errors`, `warnings` with stable, testable codes.
- Store is ready when: tenant active/trial, `subdomain` set, `StoreSetting` + `ThemeSetting` exist, at least one active payment method, at least one active shipping rate, and at least one visible and sellable product.
- Simple product is sellable if: `status=active`, not future-published, non-negative price, and inventory is untracked, allows backorders, or `available > 0`.
- Variable product is ready only when at least one active variant has sellable variant-level inventory.
- Custom domain or TLS is not required for the readiness gate.

Stable readiness codes: `missing_payment_method`, `missing_shipping_rate`, `no_sellable_products`, `product_missing_inventory`, `variable_product_missing_variants`, `variable_product_missing_options`, `variable_product_no_sellable_variants`, `invalid_product_price`.

## 9. Security and Audit Contract

- Sensitive operations must log `actor`, `event`, `auditable`, `old_values`, `new_values`, and `metadata`.
- Never log raw PII (phone, IP) in application logs. Use masking or hashing.
- Audit logs are immutable from the admin interface.
- Any security-sensitive admin action requires an `AUDIT_MATRIX.md` entry before implementation.
- Secrets are never placed in docs, tests, or committed config.
- 2FA reset: use only `security:reset-two-factor` or a documented incident procedure. Reset clears secret/recovery codes but does not change password, roles, tenant memberships, or revoke active sessions.

## 10. Operations Contract

- Any production-facing feature needs a health/readiness consideration.
- If a feature depends on queue, scheduler, storage, or search — document it in a runbook.
- Readiness must not give false positives when PostgreSQL, Redis, storage, queue, or Meilisearch are unavailable.
- Any deployment change needs a clear smoke path and rollback notes.
- Backup/restore and monitoring are not optional later features — they are required before beta/production.
- Real staging is not proven until external proof is recorded, not just by running a local ephemeral smoke.
