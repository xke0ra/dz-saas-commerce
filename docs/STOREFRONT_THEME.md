# Storefront Theme Sections

Last updated: 2026-04-28

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

## Mobile Checkout Polish

Current mobile-oriented improvements:

- header uses a responsive grid and horizontally scrollable navigation on narrow screens
- cart page shows selected item count
- cart page includes a mobile CTA that jumps to the checkout form
- quick order submit button is full width for easier tapping

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
