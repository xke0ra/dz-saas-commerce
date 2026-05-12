# Architecture

Last updated: 2026-05-12

This document describes the current architecture of `dz-saas-commerce` based on the repository state. It is an execution reference, not a marketing overview.

## Product Shape

`dz-saas-commerce` is a modular monolith for a multi-tenant Algerian SaaS e-commerce platform.

The platform currently has three main surfaces:

1. Laravel backend in `backend/`
2. Filament dashboard panels in `backend/app/Filament`
3. Next.js customer storefront in `storefront/`

The product is not a marketplace first. Each tenant owns its own store and customer storefront. The architecture must remain compatible with a future marketplace mode.

## Backend Stack

- Laravel 13
- PHP 8.3+
- Filament 5.6+
- PostgreSQL
- Redis
- Laravel queues and scheduler
- Laravel Scout with Meilisearch
- MinIO locally, S3-compatible storage later
- Pest for tests

Important backend entry points:

- `backend/routes/api.php`: public storefront API routes.
- `backend/routes/web.php`: web routes such as invitation acceptance, order slip printing, and vendor tenant switching.
- `backend/bootstrap/providers.php`: application and Filament panel providers.
- `backend/app/Providers/AppServiceProvider.php`: scoped tenant context registration and policy registration.
- `backend/database/seeders/AlgeriaGeographySeeder.php`: Algerian wilayas and communes checkout dataset.

## Frontend Stack

- Next.js 15
- React 19
- TypeScript
- Tailwind CSS
- shadcn-style local UI primitives
- React Hook Form and Zod where forms need validation
- Playwright for e2e tests

Important storefront entry points:

- `storefront/src/lib/store-context.ts`: resolves active storefront context from host or fallback store identifier.
- `storefront/src/lib/api.ts`: wraps backend API calls.
- `storefront/src/app/page.tsx`: storefront home.
- `storefront/src/app/products/*`: product listing and details.
- `storefront/src/components/storefront/quick-order-form.tsx`: current quick order UI.
- `storefront/src/components/storefront/cart-provider.tsx`: store-scoped client cart state.
- `storefront/src/components/storefront/cart-checkout.tsx`: cart order UI that submits item IDs and quantities to Laravel.
- `storefront/src/app/cart/page.tsx`: public cart checkout page.
- `storefront/src/components/storefront/store-trust-badges.tsx`: localized storefront trust section.
- `storefront/src/components/storefront/store-contact-strip.tsx`: store contact and legal section.
- `storefront/src/lib/seo.ts`: store-aware canonical, OpenGraph, and robots metadata helpers.
- `storefront/src/lib/structured-data.ts`: basic Store, Product, and BreadcrumbList JSON-LD builders.
- `storefront/src/app/sitemap.ts`: dynamic storefront sitemap.
- `storefront/src/app/robots.ts`: dynamic storefront robots policy.

## Infrastructure

Local infrastructure is declared in `docker-compose.yml`:

- `postgres`
- `redis`
- `meilisearch`
- `minio`
- `mailpit`

Production infrastructure is not finalized yet. The architecture should remain compatible with:

- Laravel Octane with FrankenPHP or RoadRunner
- Redis-backed cache, sessions, and queues
- CDN in front of the storefront and assets
- S3-compatible object storage
- Managed PostgreSQL with backups and point-in-time recovery

Production container foundation now exists as a first baseline:

- `backend/Dockerfile`: PHP-FPM runtime with Composer dependencies and Vite asset build.
- `storefront/Dockerfile`: Next.js production runtime using pnpm.
- `backend/.env.production.example`
- `storefront/.env.production.example`
- `docs/PRODUCTION_READINESS.md`
- `docs/BACKUP_RESTORE_RUNBOOK.md`
- `docs/REVERSE_PROXY_RUNBOOK.md`
- `docs/QUEUE_SCHEDULER_RUNBOOK.md`
- `docs/MONITORING_ALERTING_RUNBOOK.md`
- `docs/adr/`
- `deploy/reverse-proxy/nginx-edge.conf.example`
- `deploy/backup/`

This does not yet make production readiness complete. Reverse proxy staging deployment, CI image builds, deployed backup schedules, restore drill execution, process supervision deployment, centralized log aggregation, alert routing, and error tracking integration remain required before beta/production. Health/readiness, backup/restore documentation, backup automation examples, reverse proxy strategy, queue/scheduler supervision documentation, and monitoring/alerting documentation now exist as baselines.

## Backend Domain Layout

The codebase currently uses Laravel folders with domain-oriented namespaces:

- `app/Actions`: business operations.
- `app/Data`: structured data objects.
- `app/Enums`: statuses, roles, permissions, and domain constants.
- `app/Http/Controllers`: thin HTTP controllers.
- `app/Http/Requests`: API validation.
- `app/Http/Resources`: API serialization.
- `app/Jobs`: background work.
- `app/Models`: Eloquent models.
- `app/Observers`: audit and side effects.
- `app/Policies`: authorization.
- `app/Support`: domain support classes.

The target architecture remains a modular monolith. If the codebase grows further, modules should be introduced by domain without splitting into microservices.

## Current Major Domains

Implemented or partially implemented domains:

- Tenancy
- Identity and RBAC
- Stores
- Catalog
- Inventory
- Checkout
- Orders
- Payments
- Shipping
- Returns
- Billing and subscriptions
- Coupons
- Domains
- Themes and store settings
- Analytics
- Audit
- Support

## Filament Panels

Current panel providers:

- `App\Providers\Filament\AdminPanelProvider`
  - Path: `/admin`
  - Panel id: `admin`
  - Access: super admin only through `User::canAccessPanel()`
  - Resources: `App\Filament\Resources`

- `App\Providers\Filament\VendorPanelProvider`
  - Path: `/vendor`
  - Panel id: `vendor`
  - Access: super admin or tenant user with `stores.view`
  - Resources: `App\Filament\Vendor\Resources`
  - Tenant middleware: `ResolveTenantFromRequest`
  - Tenant switcher page: `/vendor/switch-tenant`

- `App\Providers\Filament\SupportPanelProvider`
  - Path: `/support`
  - Panel id: `support`
  - Access: super admin or platform support
  - Resources: `App\Filament\Support\Resources`

Filament is not used for the public customer storefront.

## Public API

The current storefront API is REST-first:

- `GET /api/storefront/geography/wilayas`
- `GET /api/storefront/geography/communes`
- `GET /api/storefront/resolve`
- `GET /api/storefront/{store}/home`
- `GET /api/storefront/{store}/products`
- `GET /api/storefront/{store}/products/{slug}`
- `GET /api/storefront/{store}/categories`
- `GET /api/storefront/{store}/categories/{slug}`
- `GET /api/storefront/{store}/search`
- `POST /api/storefront/{store}/checkout`
- `GET /api/storefront/{store}/track-order`

The storefront must never calculate trusted totals, discounts, shipping fees, inventory validity, payment status, or subscription limits. Laravel remains the source of truth.

The checkout endpoint supports both single-product quick order payloads and cart payloads with `items: [{ product_id, quantity }]`. Cart display data is client-side only and must not be trusted by Laravel.

Current public API hardening notes:

- Public `StoreResource` does not expose `tenant_id`; storefront store resolution should avoid unnecessary internal tenant identifiers.
- Cart checkout rejects duplicate product IDs at request validation and inside quick order creation before inventory reservation.
- `Store` is a documented tenancy exception without a global current-tenant scope; explicit `forTenant(null)` fails closed, while public store resolution must still enforce active store and active/trial tenant checks.

## Storefront SEO

The storefront generates dynamic metadata per resolved store. Public crawl routes are:

- `GET /sitemap.xml`
- `GET /robots.txt`

Sitemaps include home, product listing, paginated product detail pages, category pages, and enabled legal pages. Robots disallows customer-action pages such as cart, search results, and track-order.

Current scale caveat: sitemap generation consumes product pagination, but very large stores still need sitemap index support before approaching per-sitemap URL limits.

Canonical and OpenGraph metadata are built from the active request host unless `NEXT_PUBLIC_STOREFRONT_BASE_URL` or `STOREFRONT_BASE_URL` is configured. This keeps local development, subdomains, and future custom domains compatible.

## Business Logic Rule

Business logic belongs in:

- `app/Actions`
- `app/Support`
- data objects in `app/Data`
- policies for authorization
- observers only for small side effects and audit-oriented reactions

Business logic must not be placed directly inside Filament resources, controllers, or Next.js components.

## Persistence Rules

- PostgreSQL is the main database.
- Tenant-owned tables use `tenant_id`.
- ULIDs are used for many business entities.
- Cross-tenant relational consistency should be enforced with database constraints where practical.
- JSON/JSONB is allowed for settings and metadata, not core relational facts.
- Money is stored as integer minor units.
- DZD is the default currency.

## Algerian Geography

The current checkout geography dataset seeds 58 active wilayas and 1541 active communes. The dataset is documented in `docs/ALGERIA_GEOGRAPHY.md`.

The 2026 territorial reform to 69 wilayas is tracked as a data migration risk. It should not be activated in checkout until the 1541 communes are mapped to the new wilaya structure and shipping-rate migration rules are tested.

## Events, Jobs, And Observers

Current side effects are mostly implemented with observers and jobs. Future high-impact workflows should prefer explicit events and jobs when the action crosses domain boundaries or can run asynchronously.

Good job candidates:

- subscription lifecycle reminders
- domain verification
- search indexing
- image processing
- notification delivery
- analytics aggregation

## Performance Direction

The architecture should move toward:

- cached storefront reads
- no N+1 queries in Filament tables or storefront endpoints
- queue-backed slow side effects
- Octane readiness
- CDN-ready public assets
- measured query optimization instead of speculative changes

The storefront currently favors correctness with `force-dynamic` pages and `cache: "no-store"` API fetches. Production scaling should introduce explicit cache/revalidation rules by route and data type.

## Security Direction

Before commercial launch, the architecture must include:

- 2FA for super admins and tenant owners
- stricter rate limits for sensitive actions
- a green storefront dependency audit, maintained locally and in CI; as of 2026-05-12 the storefront is on Next `15.5.18`
- security headers baseline and production CSP tightening
- trusted proxy configuration for forwarded HTTPS/IP headers
- tested backup and restore process; runbook exists but drill execution is still required
- explicit audit trails for financial, tenant, order, and staff actions
- least-privilege production credentials

## Architecture Change Rule

Any future structural change must update this file and `docs/PROJECT_DEEP_ANALYSIS_AND_AI_ROADMAP_AR.md` when it changes one of these:

- module boundaries
- tenancy behavior
- authorization model
- API contracts
- storage/search/queue behavior
- deployment or verification workflow
