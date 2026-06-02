# System Invariants

Last updated: 2026-06-02

Every invariant below is a rule that must not be violated by code, docs,
operations, or agent behavior.

| Invariant | Rationale | Affected Domains | Validation Method | Related Tests | Risk If Violated |
|---|---|---|---|---|---|
| Tenant-owned data must carry `tenant_id` | Shared database tenancy depends on row isolation | Tenancy, all commerce domains | migrations, models, policies | tenancy integrity tests | Cross-tenant data exposure |
| `withoutGlobalScope('current_tenant')` requires explicit tenant filtering | Removing the scope opens the full shared database | Tenancy, checkout, billing, inventory | code review, grep, tests | tenancy, checkout tests | Data leak or wrong tenant mutation |
| `Store` queries are tenancy-sensitive even when public | Store participates in public host resolution | Storefront, tenancy | resolver tests, code review | storefront availability tests | Wrong store exposed |
| Backend owns totals and money | Client totals are untrusted | Checkout, payments, coupons, shipping | API tests, payload review | quick checkout tests | Fraud, under/overcharging |
| Storefront must never send trusted `subtotal`, `discount`, `shipping_fee`, or `total` | UI state can be modified by customers | Storefront, checkout | type review, request validation | storefront e2e, backend checkout | Revenue loss |
| `ProductType` controls simple vs variable behavior | Product purchasing semantics depend on type | Catalog, variants, checkout | enum and checkout tests | variant model/schema, quick checkout | Wrong inventory or invalid purchases |
| Simple products use `product_variant_id IS NULL` inventory | Sellable unit is product-level | Inventory, checkout | migration constraints, tests | variant inventory tests | Duplicate or missing inventory |
| Variable products require variant-level inventory | Sellable unit is variant-level | Variants, inventory, checkout | readiness and checkout tests | variant inventory tests | Overselling or wrong SKU |
| Checkout must reject parent checkout for variable products | A variable parent is not a sellable unit | Checkout, storefront | request/action tests | quick checkout tests | Ambiguous price/inventory |
| Checkout must reject variant id for simple products | Simple product has no variant sellable unit | Checkout | request/action tests | quick checkout tests | Invalid order item |
| Inventory reservation, order creation, payment record, and stock movement are transactional | Partial checkout writes corrupt operations | Checkout, inventory, payments | transaction review, tests | quick checkout tests | Missing reservation or orphan order |
| Inventory quantity is settled or released through lifecycle actions | Direct mutation loses ledger history | Inventory, orders, returns | action review, stock movement assertions | inventory and order tests | Stock drift |
| `StockMovement` is append-oriented operational ledger | History is needed for reconciliation | Inventory | tests and audit review | stock movement tests | No traceability |
| `AuditLog` is not editable through admin UI | Audit integrity | Security, audit | policy tests | audit tests | Tampered audit trail |
| 2FA enforcement must not be bypassed as a fix | Security flow must remain intact | Security, Filament panels | security tests | 2FA tests | Account takeover risk |
| Emergency 2FA reset must require reason and target | Operator action must be auditable | Security, operations | command tests | 2FA reset command tests | Untraceable security reset |
| Production readiness cannot be claimed without current evidence | Docs must not overclaim | Operations | evidence review, smoke proof | smoke scripts | False launch readiness |
| Dated evidence is not current live proof | Infrastructure can change after evidence | Operations | rerun smoke when needed | staging smoke proof | Stale operational assumptions |
| Production `APP_DEBUG` must be false and `APP_KEY` present | Runtime safety | Operations, security | health readiness | system health tests | Sensitive leak or broken encryption |
| Public API must not expose `tenant_id` without documented reason | Tenant internals are not public data | Storefront API, security | resource review | storefront tests | Data exposure |
| Secrets must not be committed in docs or examples | Secret hygiene | All | grep, code review | security scripts | Credential leak |
| Feature gates must run before restricted SaaS operations | Billing limits are platform control | Billing, catalog, domains, staff | action review, tests | billing tests | Plan limit bypass |
| Migrations must be forward-safe for deploy | Partial deploys must not break old code | Database, operations | migration runbook | migration/status checks | Downtime or data loss |
| Queue and scheduler behavior must be documented for production-facing work | Async work can silently fail | Operations, billing, domains | runbook update | system/feature tests | Missed jobs |
