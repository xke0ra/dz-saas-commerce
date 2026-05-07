# Storefront Cart And Checkout

Last updated: 2026-04-28

This document defines the current storefront cart contract.

## Purpose

The cart is a customer-facing convenience layer in the Next.js storefront. It is not a pricing, discount, shipping, inventory, or payment authority.

Laravel remains the source of truth for:

- product price
- product availability
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
- `storefront/src/app/cart/page.tsx`
- `storefront/src/components/storefront/quick-order-form.tsx`
- `storefront/src/lib/types.ts`
- `storefront/tests/e2e/storefront.spec.ts`

## Storage

The cart is stored in `localStorage` with a store-specific key:

```text
dz-saas-commerce:cart:{storeId}
```

The stored item shape is intentionally limited to display data and quantity:

```ts
{
  id: string;
  name: string;
  slug: string;
  sku: string | null;
  price_minor: number;
  currency: string;
  image_url: string | null;
  quantity: number;
}
```

The displayed price is only a storefront hint. The checkout request sends only product IDs and quantities as trusted input candidates.

## Checkout Payload

Single-product quick order:

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

Cart order:

```json
{
  "items": [
    {
      "product_id": "prod_01",
      "quantity": 2
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

## UX Rules

- Product cards and product details can add items to cart.
- The header cart link shows the current cart quantity.
- `/cart` displays selected items, allows quantity changes, and submits via the same quick order form.
- After successful cart checkout, the local cart is cleared while the confirmation remains visible.
- The cart page tells the customer that final totals, shipping, discounts, and stock are confirmed when the order is sent.

## Test Coverage

Current storefront e2e coverage includes:

- storefront home and product listing
- product quick COD order
- cart COD order with `items` payload
- order tracking

Verification commands:

```bash
cd storefront
pnpm typecheck
pnpm build
pnpm test:e2e
```

Backend checkout validation is covered separately:

```bash
cd backend
php artisan test tests/Feature/Checkout/QuickCheckoutTest.php
```
