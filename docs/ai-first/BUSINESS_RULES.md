# Business Rules

Last updated: 2026-06-02

## Tenancy

- A tenant is the boundary for commercial data.
- Tenant users receive permissions through roles and overrides.
- Super admins can cross tenant boundaries only through documented platform paths.
- Public storefront resolution may identify a store by active custom domain,
  store domain, or subdomain, but sensitive operations still require explicit
  store/tenant resolution.

## Store Readiness

- `StoreReadinessChecker` is a domain gate, not a deployment gate.
- Store readiness requires tenant active/trial, subdomain, active target status,
  store settings, theme settings, active COD payment method, active shipping
  rate, and at least one sellable product.
- Legal content gaps are warnings, not blocking readiness errors.
- Custom domain/TLS is not part of the readiness gate.

## Catalog And Variants

- Products are `simple` or `variable`.
- Simple products are sold through product-level inventory.
- Variable products are sold through active variants.
- Variant price can override product price; otherwise effective price falls back
  to the parent product price.
- Product detail responses must not expose tenant internals or cost metadata.

## Checkout

- Storefront sends customer fields, delivery selection, optional coupon, and
  items.
- Backend calculates all financial fields.
- Checkout supports single-product quick order shape and cart `items` shape.
- Cart cannot mix a parent product and variants for the same product.
- Duplicate product or duplicate variant lines are rejected.
- Checkout creates order, order items, status history, payment record, coupon
  redemption when applicable, and stock reservation inside the backend flow.
- `Idempotency-Key` is supported; duplicate-window fallback is best effort only.

## Inventory

- Inventory is tracked by sellable unit.
- Simple sellable unit: `tenant_id + product_id + product_variant_id IS NULL`.
- Variable sellable unit: `tenant_id + product_variant_id`.
- Reserved quantity is not deducted from quantity until lifecycle settlement.
- Manual inventory adjustment requires permission and reason.
- Manual adjustments write stock movement and audit log.

## Orders, Shipping, Returns

- Order status changes should go through actions, not ad hoc UI mutation.
- Shipment status changes should go through shipping transition actions.
- Return handling should go through return transition actions.
- Restocking a return must write stock movement.

## Payments And Billing

- Manual payments are first-class; external gateway integration is deferred.
- COD/manual payment states must be updated by authorized actors.
- Subscription lifecycle can create invoices, reminders, grace periods, past due
  state, and suspension.
- Feature gates must enforce plan limits before restricted operations.

## Operations

- Staging evidence must be recorded after an external smoke, not inferred from
  local setup.
- Production readiness requires monitoring, rollback proof, backups, restore
  proof, health checks, and hardening.
- Health readiness checks database, cache, queue, storage, Redis if required,
  search if Meilisearch is configured, and production runtime safeguards.
