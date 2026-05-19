# Storefront Cart And Checkout

Last updated: 2026-05-19

This document defines the current storefront cart and checkout contract.

## Purpose

The cart is a customer-facing convenience layer in the Next.js storefront. It is not a pricing, discount, shipping, inventory, or payment authority.

Laravel remains the source of truth for:

- product price
- variant price override
- product and variant availability
- inventory validity
- coupon validity
- shipping fee
- subtotal
- discount
- total
- payment status
- subscription limits

## Current Files

- `storefront/src/components/storefront/cart-provider.tsx`
- `storefront/src/components/storefront/add-to-cart-button.tsx`
- `storefront/src/components/storefront/cart-nav-link.tsx`
- `storefront/src/components/storefront/cart-checkout.tsx`
- `storefront/src/components/storefront/product-variant-purchase-panel.tsx`
- `storefront/src/components/storefront/quick-order-form.tsx`
- `storefront/src/app/cart/page.tsx`
- `storefront/src/app/products/[slug]/page.tsx`
- `storefront/src/lib/types.ts`
- `storefront/tests/e2e/storefront.spec.ts`

## Storage

The cart is stored in `localStorage` with a store-specific key:

```text
dz-saas-commerce:cart:{storeId}
```

Stored item shape is display-only plus quantity:

```ts
{
  id: string;
  product_id?: string;
  product_variant_id?: string | null;
  name: string;
  slug: string;
  sku: string | null;
  variant_title?: string | null;
  selected_options?: Record<string, string>;
  price_minor: number;
  currency: string;
  image_url: string | null;
  quantity: number;
}
```

For simple products, `id` is the product id. For variants, `id` is a storefront cart key such as `product_id:product_variant_id`, while `product_id` remains the parent product id sent to Laravel.

Displayed price is only a storefront hint. The checkout request sends only product/variant IDs and quantities as trusted input candidates.

## Variant Behavior

- Product detail API exposes `type`, active `variants`, `options`, selected options, effective price, and availability only for variable products.
- The product detail picker lets the customer select option values.
- Checkout is disabled until a valid available variant is selected for variable products.
- Cart items for variants use product+variant identity to avoid merging different variants.
- Storefront sends `product_variant_id` to Laravel for selected variants.
- Laravel still revalidates product type, variant status, tenant/product ownership, price, inventory, and totals.

## Checkout Payload

Single-product simple quick order:

```json
{
  "product_id": "prod_01",
  "quantity": 1,
  "full_name": "Ahmed Demo",
  "phone": "0555123456",
  "wilaya_id": 16,
  "commune_id": 1601,
  "address": "Alger Centre",
  "delivery_type": "home",
  "coupon_code": null,
  "note": null
}
```

Single-product variant quick order:

```json
{
  "product_id": "prod_variable_01",
  "product_variant_id": "var_01",
  "quantity": 1,
  "full_name": "Ahmed Demo",
  "phone": "0555123456",
  "wilaya_id": 16,
  "commune_id": 1601,
  "address": "Alger Centre",
  "delivery_type": "home",
  "coupon_code": null,
  "note": null
}
```

Cart order:

```json
{
  "items": [
    {
      "product_id": "prod_01",
      "quantity": 2
    },
    {
      "product_id": "prod_variable_01",
      "product_variant_id": "var_01",
      "quantity": 1
    }
  ],
  "full_name": "Ahmed Demo",
  "phone": "0555123456",
  "wilaya_id": 16,
  "commune_id": 1601,
  "address": "Alger Centre",
  "delivery_type": "home",
  "coupon_code": null,
  "note": null
}
```

## Backend Rejections

Laravel rejects:

- duplicate parent product rows in one checkout.
- duplicate variant rows in one checkout.
- mixing parent product and variants for the same product in one cart.
- variant id for a `simple` product.
- missing variant id for a `variable` product.
- inactive variant.
- variant from another product.
- variant from another tenant.
- variant inventory fallback to parent inventory when variant inventory is missing.

## UX Rules

- Product cards and product details can add items to cart.
- Variable product detail must route purchase through the variant picker.
- The header cart link shows the current cart quantity.
- `/cart` displays selected items, variant title/options when present, allows quantity changes, and submits via the same quick order form.
- After successful cart checkout, the local cart is cleared while the confirmation remains visible.
- The cart page tells the customer that final totals, shipping, discounts, and stock are confirmed when the order is sent.

## Test Coverage

Current storefront e2e coverage includes:

- storefront home and product listing
- storefront SEO/crawl route smoke checks
- product variant choices on product detail
- mobile navigation
- product quick COD order
- simple product quick order without `product_variant_id`
- legacy product payload without `type` treated as simple
- cart COD order with `items` payload
- order tracking

Backend checkout validation is covered separately:

```bash
cd backend
php artisan test tests/Feature/Checkout/QuickCheckoutTest.php
```

Storefront verification:

```bash
cd storefront
corepack enable
corepack prepare pnpm@11.1.2 --activate
pnpm typecheck
pnpm build
pnpm test:e2e
```
