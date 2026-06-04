# Storefront Theme Sections

Last updated: 2026-05-31

This document defines the current customer storefront presentation layer.

## Purpose

The storefront must feel like a real Algerian merchant store, not only a technical catalog. Theme sections should improve trust, clarity, and conversion while keeping Laravel as the source of truth for all business values.

## Current Home Sections

The current home page includes:

- hero section using store theme title, subtitle, and optional hero image
- active category links
- trust badges
- featured products
- contact and legal strip

Current files:

- `storefront/src/app/page.tsx`
- `storefront/src/components/storefront/store-trust-badges.tsx`
- `storefront/src/components/storefront/store-contact-strip.tsx`
- `storefront/src/components/storefront/store-header.tsx`
- `storefront/src/components/storefront/store-footer.tsx`
- `storefront/src/lib/i18n.ts`

## Trust Badges

Trust badges are localized in Arabic and French and currently cover:

- cash on delivery
- home or desk delivery
- phone confirmation
- clear legal/contact information

These are storefront trust messages only. They must not imply that delivery, payment, return, or stock decisions are final without backend confirmation.

## Contact And Legal Strip

The contact strip uses `store_setting` fields:

- public phone
- public email
- seller address
- enabled legal pages

Legal links only render when the page is enabled in store settings.

## Product Detail And Variant Picker

Added in ADR 0013 (2026-05-18). The product detail page renders two distinct flows depending on `product.type`:

### Simple Products

- No variant picker rendered.
- A single "Quick Order" form is shown directly.
- `product_variant_id` is never sent in the checkout payload for simple products.
- Quick-order form sends: `product_id`, `quantity`, customer fields, wilaya/commune, payment method.

### Variable Products

- `product-variant-purchase-panel.tsx` renders options (size, color, etc.) as selectable buttons.
- The panel resolves the active variant from the selected combination of option values.
- Displayed price updates to `variant.price_minor` when the variant has an override; falls back to product base price.
- Availability indicator shows `available_quantity` from variant-level inventory.
- Add-to-cart sends: `product_id` + `product_variant_id` + `quantity`.
- Checkout rejects invalid combinations server-side regardless of what the picker displays.

Current files:

- `storefront/src/app/products/[slug]/page.tsx`
- `storefront/src/components/storefront/product-variant-purchase-panel.tsx`
- `storefront/src/components/storefront/quick-order-form.tsx`
- `storefront/src/components/storefront/cart-checkout.tsx`
- `storefront/src/components/storefront/cart-provider.tsx`

Cart sellable unit key: `product_id + product_variant_id` (null for simple products). The cart must not allow the same sellable unit twice — duplicate detection happens at both the cart provider and at the checkout request validation layer.

## Mobile Checkout Polish

Current mobile-oriented improvements:

- header uses a responsive grid and horizontally scrollable navigation on narrow screens
- cart page shows selected item count
- cart page includes a mobile CTA that jumps to the checkout form
- quick order submit button is full width for easier tapping
- variant option buttons are touch-friendly with adequate tap target size

## Testing

Current Playwright coverage verifies:

- home page trust badges render
- contact section renders
- mobile navigation remains usable
- cart count updates on mobile
- mobile cart checkout CTA is visible

Verification commands:

```bash
cd storefront
pnpm typecheck
pnpm build
pnpm test:e2e
```

## Next Theme Work

- richer empty and error states
- branded unavailable page
- theme section configuration from backend `layout_settings`
- optional promotional sections
- better product media presentation
- mobile checkout step grouping if the form becomes longer
- variant sold-out state UI (disabled picker + "out of stock" label)
- variant image switching when a variant has its own image
