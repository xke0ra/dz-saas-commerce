# Critical Paths

Last updated: 2026-06-02

## Checkout Critical Path

Files:

- `backend/routes/api.php`
- `backend/app/Http/Requests/Storefront/QuickCheckoutRequest.php`
- `backend/app/Actions/Checkout/CreateQuickOrder.php`
- `backend/app/Support/Checkout/*`
- `storefront/src/components/storefront/quick-order-form.tsx`
- `storefront/src/components/storefront/cart-checkout.tsx`
- `storefront/src/lib/types.ts`

Risks:

- money calculation errors
- tenant leak
- invalid variant purchase
- duplicate orders
- missing inventory reservation

## Tenant Resolution Critical Path

Files:

- `backend/app/Support/Tenancy/TenantResolver.php`
- `backend/app/Http/Middleware/ResolveTenantFromRequest.php`
- `backend/app/Models/Concerns/BelongsToTenant.php`
- `storefront/src/lib/store-context.ts`

Risks:

- wrong store/tenant context
- public host trust mistake
- cross-tenant query

## Product Variant Critical Path

Files:

- variant migrations
- `backend/app/Enums/ProductType.php`
- `backend/app/Models/Product*.php`
- `backend/app/Support/Catalog/ProductVariantOptionValueValidator.php`
- `storefront/src/components/storefront/product-variant-purchase-panel.tsx`

Risks:

- parent product sold as variable item
- wrong SKU or price
- wrong inventory row

## 2FA Critical Path

Files:

- `backend/app/Filament/Pages/TwoFactorAuthenticationPage.php`
- `backend/app/Filament/Pages/TwoFactorChallengePage.php`
- `backend/app/Http/Middleware/EnsurePanelTwoFactor.php`
- `backend/app/Support/Auth/*`
- `backend/routes/console.php`

Risks:

- login/setup loop
- bypassed enforcement
- unaudited emergency reset

## Production/Staging Critical Path

Files:

- `deploy/staging/*`
- `deploy/reverse-proxy/*`
- `deploy/backup/*`
- `docs/PRODUCTION_READINESS.md`
- `docs/STAGING_DEPLOYMENT_RUNBOOK_AR.md`
- `docs/evidence/*`

Risks:

- false readiness claims
- broken asset URL/TLS proxy behavior
- missing backup/restore proof
- stale staging evidence
