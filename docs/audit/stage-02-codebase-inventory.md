# Stage 2: Actual Codebase Inventory

**Audit Date:** 2026-10-01  
**Audit Branch:** `main`  
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`  

---

## 1. System Inventory Summary

Total tracked files audited: **837**.  
Breakdown:
- Backend (`backend/`): 654 files
- Storefront (`storefront/`): 62 files
- Documentation (`docs/`): 75 files
- Deployment (`deploy/`): 23 files
- Scripts (`scripts/`): 7 files
- Devcontainer (`.devcontainer/`): 5 files
- CI Workflows (`.github/`): 3 files
- Root configurations: 8 files

---

## 2. Detailed Subsystem Classification

| Subsystem / Component | Path(s) | Status | Evidence / Implementation Details |
| :--- | :--- | :--- | :--- |
| **Tenancy Core** | `backend/app/Support/Tenancy/*`, `backend/app/Models/Concerns/BelongsToTenant.php` | **IMPLEMENTED** | `CurrentTenant` runtime singleton, `TenantResolver`, `TenantSwitcher`, `BelongsToTenant` Eloquent global scope with automatic `tenant_id` assignment on creating. |
| **Tenancy Models** | `backend/app/Models/Tenant.php`, `TenantUser.php`, `TenantInvitation.php` | **IMPLEMENTED** | Database migrations and models present with soft deletes, statuses, roles, and invitation tokens. |
| **Tenancy Enforcement** | `backend/app/Http/Middleware/ResolveTenantFromRequest.php` | **IMPLEMENTED** | Middleware resolves tenant for `/vendor` panel based on session, request, or user default store, applying `CurrentTenant::set()`. |
| **Identity & RBAC** | `backend/app/Models/User.php`, `TenantUser.php`, `Enums/PlatformRole.php`, `Enums/TenantRole.php`, `Enums/TenantPermission.php` | **IMPLEMENTED** | Platform roles (Super Admin, Support) and Tenant roles (Owner, Manager, Staff) with fine-grained granular permission checks in policies. |
| **Two-Factor Auth (2FA)** | `backend/app/Support/Auth/TwoFactorAuthentication.php`, `EnsurePanelTwoFactor.php`, `Filament/Pages/TwoFactor*` | **IMPLEMENTED** | TOTP secret generation, QR code, recovery codes, confirmation, and panel challenge enforcement. |
| **Emergency 2FA Reset** | `backend/routes/console.php` (`security:reset-two-factor`) | **PARTIAL** | Command accepts `--user-id`, `--email`, `--reason`, `--actor-id`, `--dry-run`, `--confirm`. Resets 2FA secret and recovery codes, writes audit log. **Gap:** Active session revocation is not implemented; existing authenticated sessions remain active. |
| **Stores & Domains** | `backend/app/Models/Store.php`, `Domain.php`, `StoreSetting.php`, `ThemeSetting.php` | **IMPLEMENTED** | Store models, theme and settings JSON columns/schemas, domain verification action (`VerifyDomainOwnership`). |
| **Store Readiness Gate** | `backend/app/Support/Readiness/StoreReadinessChecker.php`, `EvaluateStoreReadiness.php` | **IMPLEMENTED** | Validates tenant status, subdomain, store settings, theme settings, active COD payment method, active shipping rate, and at least 1 sellable product. |
| **Catalog & Products** | `backend/app/Models/Category.php`, `Product.php`, `ProductImage.php`, `Enums/ProductType.php` | **IMPLEMENTED** | Categories, products, primary/secondary images with unique constraints, `ProductType` (simple vs variable). |
| **Product Variants** | `backend/app/Models/ProductOption.php`, `ProductOptionValue.php`, `ProductVariant.php`, `ProductVariantOptionValue.php` | **IMPLEMENTED** | Variant generation, option value linking, variant price overrides, variant SKU, unique compound variant combination indices. |
| **Product Search** | `backend/app/Actions/Catalog/SearchStorefrontProducts.php` | **IMPLEMENTED** | Laravel Scout with Meilisearch engine fallback. |
| **Inventory Tracking** | `backend/app/Models/InventoryItem.php`, `Actions/Inventory/*` | **IMPLEMENTED** | Tracks `quantity`, `reserved_quantity`, `allow_backorders`. Differentiates simple products (`product_variant_id IS NULL`) and variable products. |
| **Stock Movement Ledger** | `backend/app/Models/StockMovement.php`, migration `create_stock_movements_table` | **IMPLEMENTED** | Append-only ledger recording quantity before/after, delta, reason (`StockMovementReason`), actor, and reference entity. |
| **Checkout Backend** | `backend/app/Actions/Checkout/CreateQuickOrder.php`, `QuickCheckoutRequest.php` | **IMPLEMENTED** | Handles single-item quick orders and multi-item carts. Atomically recalculates pricing, validates product types, validates variant tenant ownership, reserves inventory, creates order, payment, and status history within a database transaction. |
| **Checkout Idempotency** | `backend/app/Models/CheckoutIdempotencyRecord.php`, `PruneCheckoutIdempotencyRecords.php` | **IMPLEMENTED** | Locks requests by `Idempotency-Key` or payload fingerprint to prevent duplicate order placement; artisan command prunes expired keys. |
| **Orders Lifecycle** | `backend/app/Models/Order.php`, `OrderItem.php`, `OrderStatusHistory.php`, `Actions/Orders/*` | **IMPLEMENTED** | Strict state machine with dedicated action classes for transitions: Confirmed, Processing, Packed, Shipped, Out for Delivery, Delivered, Cancelled. |
| **Payments (Manual/COD)** | `backend/app/Models/Payment.php`, `PaymentMethod.php`, `Actions/Payments/*` | **IMPLEMENTED** | Cash on delivery and manual bank transfer confirmation, rejection, failure, and refunds. |
| **Payments (Gateways)** | Automated payment gateways (Satim/CIB/Edahabia/Stripe) | **NOT FOUND** | Explicitly deferred per ADR 0009 ("Manual payments first"). |
| **Shipping Core** | `backend/app/Models/ShippingCompany.php`, `ShippingRate.php`, `Shipment.php`, `Actions/Shipping/*` | **IMPLEMENTED** | Wilaya/commune shipping rates, home delivery vs desk pickup (`DeliveryType`), complete shipment lifecycle actions. |
| **Shipping Integrations** | Carrier APIs (Yalidine, ZR Express) | **NOT FOUND** | Deferred per ADR 0010 ("Internal shipping configuration first"). |
| **Returns Core** | `backend/app/Models/OrderReturn.php`, `Actions/Returns/*`, `RestockOrderReturn.php` | **IMPLEMENTED** | Return requests, approval, rejection, receiving, restock back into inventory with stock ledger movements, refund actions. |
| **Billing & Subscriptions** | `backend/app/Models/Plan.php`, `Subscription.php`, `Invoice.php`, `UsageCounter.php`, `Actions/Billing/*` | **IMPLEMENTED** | Plan feature gating, subscription status lifecycle (trial, active, past_due, suspended), invoice issuance, renewal reminders, manual payment proof confirmation. |
| **Coupons** | `backend/app/Models/Coupon.php`, `CouponRedemption.php`, `CalculateCouponDiscount.php` | **IMPLEMENTED** | Fixed and percentage discounts, min order value, expiration, max redemptions, tenant isolation. |
| **Public Storefront API** | `backend/routes/api.php`, `StorefrontController.php`, `GeographyController.php`, Resources | **IMPLEMENTED** | Endpoints for store resolution, categories, products, search, quick checkout, order tracking, wilayas, communes. |
| **Filament Admin Panel** | `backend/app/Providers/Filament/AdminPanelProvider.php`, `Filament/Resources/*` | **IMPLEMENTED** | Platform super-admin management of tenants, plans, platform users, system health. |
| **Filament Vendor Panel** | `backend/app/Providers/Filament/VendorPanelProvider.php`, `Filament/Vendor/Resources/*` | **IMPLEMENTED** | Full merchant portal for catalog, variants, orders, shipments, returns, stock, staff, settings, billing. |
| **Filament Support Panel** | `backend/app/Providers/Filament/SupportPanelProvider.php`, `Filament/Support/Resources/*` | **IMPLEMENTED** | Support staff ticket management, tenant diagnostics. |
| **Storefront App** | `storefront/src/app/*`, `storefront/src/components/*` | **IMPLEMENTED** | Next.js 15 App Router customer storefront: home, product detail, variant selector, cart, checkout, order tracking, SEO metadata, JSON-LD, sitemap, robots. |
| **Storefront Caching** | Caching and ISR | **PARTIAL** | Pages currently marked `force-dynamic` with `no-store` API client calls; proposed in ADR 0011. |
| **CORS Policy** | Explicit CORS allow-list | **NOT FOUND / DEFAULT** | Standard Laravel CORS configuration exists, but explicit domain whitelist middleware for custom domains is missing (ADR 0015). |
| **API Versioning** | Storefront API versioning | **NOT FOUND** | Routes currently unversioned under `/api/storefront/*` (ADR 0016). |
| **Error Tracking** | Sentry / Bugsnag provider integration | **NOT FOUND** | Proposed in ADR 0014; no external error tracking package installed. |
| **Local Infrastructure** | `docker-compose.yml` | **IMPLEMENTED** | Postgres 17, Redis 7, Meilisearch 1.12, MinIO, Mailpit. |
| **Devcontainer** | `.devcontainer/devcontainer.json`, `Dockerfile`, scripts | **IMPLEMENTED** | Development container definitions with post-create setup. |
| **CI/CD** | `.github/workflows/*` | **IMPLEMENTED** | Quality gates, image build/push to GHCR, ephemeral staging compose smoke test. |
| **Backup Automation** | `deploy/backup/*` | **IMPLEMENTED** | Postgres dump scripts, MinIO/S3 sync script, restore drill script, systemd timer/service units. |
| **Supervision Units** | `deploy/supervision/systemd/*` | **IMPLEMENTED** | Systemd unit examples for Laravel queue workers and artisan scheduler. |
| **Reverse Proxy** | `deploy/reverse-proxy/nginx-edge.conf.example` | **IMPLEMENTED** | Nginx reverse proxy configuration for forwarding HTTPS/IP headers and routing `/api` vs storefront. |
