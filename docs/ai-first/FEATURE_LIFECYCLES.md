# Feature Lifecycles

Last updated: 2026-06-02

## Storefront Checkout

1. Store resolves from host or fallback identifier.
2. Storefront loads product/category/home data.
3. Customer selects product or cart items.
4. Storefront submits customer and item payload.
5. Backend validates products, variants, shipping, payment method, coupon, and
   feature gates.
6. Backend reserves inventory and writes stock movement.
7. Backend creates order, items, status history, payment record, and coupon
   redemption when applicable.
8. Storefront receives safe order confirmation.

Required docs: `docs/STOREFRONT_CART.md`, domain contracts, ADR 0005, ADR 0006, ADR 0013.

## Inventory Lifecycle

1. Inventory item represents sellable unit.
2. Checkout reserves quantity.
3. Order cancellation releases reservation.
4. Delivery settlement finalizes inventory.
5. Return restock increases availability where appropriate.
6. Manual adjustment writes stock movement and audit log.

Required docs: domain contracts, ADR 0013, audit matrix.

## Product Variant Lifecycle

1. Product is marked `variable`.
2. Product options and option values are created.
3. Variants are created with option signatures.
4. Variant inventory rows are created.
5. Storefront product detail exposes active variants/options.
6. Checkout requires selected variant id.

Required docs: ADR 0013, storefront cart docs, testing strategy.

## Store Readiness Lifecycle

1. Tenant exists and is active/trial.
2. Store has subdomain and target active status.
3. Store settings and theme settings exist.
4. Active COD payment method exists.
5. Active shipping rate exists.
6. At least one visible sellable product exists.
7. Legal content warnings are surfaced but do not block readiness.

Required docs: domain contracts, readiness tests.

## Billing Lifecycle

1. Tenant subscribes to a plan.
2. Initial or renewal invoice is issued.
3. Subscription payment is recorded, confirmed, or rejected.
4. Lifecycle job processes reminders, grace periods, overdue state, and
   suspension.
5. Feature gates enforce plan limits.

Required docs: domain contracts, security baseline, testing strategy.

## Domain Verification Lifecycle

1. Tenant adds hostname/domain.
2. Verification job/action checks DNS.
3. Domain status changes to active or failed.
4. Storefront resolver can use active verified domain.

Required docs: architecture, reverse proxy runbook, production readiness.

## Staging Evidence Lifecycle

1. Operator prepares env/secrets/images.
2. Smoke script runs against external endpoint.
3. Result is copied from template into `docs/evidence/`.
4. Readiness docs link to dated proof.
5. Future claims require fresh evidence when current health matters.
