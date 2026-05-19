# ADR 0013: Product Variants And Inventory Design

Date: 2026-05-17

Last updated: 2026-05-19

Status: Accepted - schema, model, vendor management, option-value UX refinement, checkout backend support, inventory uniqueness activation, lifecycle propagation, storefront API serialization, storefront picker UI, and product type enforcement are complete.

## Context

`Product` remains the public catalog entity for SEO, category assignment, product page content, and listing/search. `ProductVariant` represents a specific sellable unit when a product is `variable`.

The implemented chain now includes:

- `ProductType` enum with `simple` and `variable`.
- `products.type` column and model cast.
- `product_options`.
- `product_option_values`.
- `product_variants`.
- `product_variant_option_values`.
- nullable `product_variant_id` on `inventory_items`, `order_items`, and `stock_movements`.
- variant models, factories, relationships, and tenant scoping.
- Vendor Filament resources for options, values, variants, and pivot records.
- validation preventing option values from another product/tenant from being linked to a variant.
- checkout support for optional `product_variant_id`.
- backend enforcement that simple products reject variants and variable products require variants.
- variant inventory uniqueness by sellable unit.
- release/settlement/restock propagation of `product_variant_id`.
- storefront product detail variant/options serialization.
- storefront variant picker UI and product+variant cart key.

## Problem

Real stores need options such as size, color, capacity, flavor, or bundle configuration. These choices can change SKU, price, inventory, availability, order snapshots, stock movements, and reporting.

Treating variants as metadata would make checkout and inventory unreliable. The platform needs a stable backend sellable-unit model.

## Decision

Use `ProductVariant` as the sellable unit for variable products.

Rules:

- `Product` is the parent display/catalog entity.
- `ProductVariant` is the purchasable unit for `ProductType::Variable`.
- `InventoryItem` can reference either a simple product or a variant.
- `OrderItem` stores both `product_id` and nullable `product_variant_id` plus snapshots.
- `StockMovement` stores `product_id`, nullable `product_variant_id`, and `inventory_item_id`.
- Laravel remains the source of truth for price, availability, inventory reservation, and order totals.

## Rejected Options

### Option A: Store variants in metadata

Rejected because metadata cannot enforce SKU uniqueness, option uniqueness, tenant integrity, checkout trust boundaries, or reliable stock reporting.

### Option C: Make every variant a standalone product

Rejected because it mixes product display with sellable unit identity, complicates SEO/category/search grouping, and pushes parent/child logic into every query.

## Implemented Schema

### `products`

- `type` string default `simple`.
- allowed values: `simple`, `variable`.
- model cast to `ProductType`.
- `products.sku` remains valid for simple products and optional/internal parent SKU for variable products.

### `product_options`

- tenant-scoped.
- belongs to product.
- unique option name per tenant/product.
- ordered by `position`.

### `product_option_values`

- tenant-scoped.
- belongs to product option.
- unique value per tenant/option.
- ordered by `position`.

### `product_variants`

- tenant-scoped.
- belongs to product.
- nullable `sku` with unique tenant SKU when present.
- required `option_signature`.
- nullable price override fields.
- `status`, `sort_order`, and metadata.
- `effectivePriceMinor()` returns variant price override or parent product price.

### `product_variant_option_values`

- pivot between variant and option value.
- tenant-scoped.
- unique variant/value pair.
- app-level validation ensures option values belong to the variant product.

### `inventory_items`

- nullable `product_variant_id`.
- simple product inventory: one row per `tenant_id + product_id` where `product_variant_id IS NULL`.
- variant inventory: one row per `tenant_id + product_variant_id` where `product_variant_id IS NOT NULL`.
- checkout does not fall back to parent inventory when a variant inventory row is missing.

### `order_items`

- nullable `product_variant_id`.
- `variant_title`.
- `variant_sku`.
- `selected_options`.
- snapshots are captured at checkout time.

### `stock_movements`

- nullable `product_variant_id`.
- `inventory_item_id` remains the exact operational movement target.
- lifecycle flows copy `product_variant_id` from the order item/sellable unit when applicable.

## Domain Rules

### Catalog

- Product can be `simple` or `variable`.
- Simple products should not be sold with `product_variant_id`.
- Variable products require active variants before they can be sold.
- Vendor UX currently manages options/values/variants/pivot records; generation and polish remain future UX work.
- Variant SKU is unique per tenant when present.
- Option values must belong to the same product and tenant as the variant.

### Inventory

- Sellable unit is:
  - product for `simple`.
  - product variant for `variable`.
- Product-level inventory is valid only for simple products.
- Variant-level inventory is required for variable products.
- Backorders remain an explicit inventory item setting.

### Checkout

- Payload supports `product_id`, optional `product_variant_id`, and `quantity`.
- `simple` product with variant id is rejected.
- `variable` product without variant id is rejected.
- inactive, cross-product, or cross-tenant variants are rejected.
- price is calculated by backend.
- order item snapshots include selected options when variant exists.
- idempotency hashing includes the full payload, including variant id when present.

### Lifecycle

- Quick checkout reservation writes `reserved`.
- Order cancellation/release writes `released`.
- Delivered/settled order inventory writes `settled`.
- Return restock writes `restocked`.
- Variant orders use variant inventory and do not touch parent inventory.

### Storefront

- Product detail API loads active variants/options/availability.
- Storefront product detail picker selects options and sends `product_variant_id`.
- Cart item key includes product+variant for variable products.
- Displayed price and availability are UX hints; checkout still revalidates in Laravel.

## Implementation History

- PR 1: Schema foundation - completed 2026-05-17.
- PR 2: Models/factories/tests - completed 2026-05-18.
- PR 3: Vendor variant management backend/forms foundation - completed 2026-05-18.
- PR 4: Checkout accepts `product_variant_id` - completed 2026-05-18.
- PR 5: Variant inventory uniqueness/schema activation - completed 2026-05-18.
- PR 6: Stock movements include `product_variant_id` in lifecycle flows - completed 2026-05-18.
- PR 7: Storefront product detail variant API serialization - completed 2026-05-18.
- PR 8: Storefront product detail variant picker UI - completed 2026-05-18.
- PR 9: Product type/simple-vs-variable enforcement - completed 2026-05-18.

## Remaining Work

- Product variant UX polish.
- Better option/variant generation.
- import/export for options/variants/SKU/price/inventory.
- filters/search improvements for variant-heavy catalogs.
- reporting UX for variant-level inventory and sales.

## Consequences

- Do not implement variants as metadata-only.
- Do not allow storefront to determine trusted price or inventory.
- Do not sell parent variable products without a variant id.
- Do not let variant lifecycle flows fall back to parent inventory.
- Keep stock movement ledger append-oriented.
- Any future change to variants must update this ADR, `DOMAIN_CONTRACTS_AR.md`, `STOREFRONT_CART.md`, and relevant tests.
