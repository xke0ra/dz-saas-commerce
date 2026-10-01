# Stage 1: Documentation and Architecture Baseline

**Audit Date:** 2026-10-01  
**Audit Branch:** `main`  
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`  

---

## 1. Intended Architecture Overview

The canonical documentation (`AI_CONTEXT.md`, `docs/ARCHITECTURE.md`, ADRs 0001–0016, and `docs/ai-first/*`) defines `dz-saas-commerce` as an **opinionated modular monolith** designed for a multi-tenant Algerian SaaS e-commerce platform.

### Core Architectural Decisions & Principles
1. **Modular Monolith (ADR 0001):** Single backend codebase structured into domain namespaces (`app/Actions`, `app/Support`, `app/Data`, `app/Models`, `app/Policies`, `app/Observers`). No microservices without a dedicated ADR.
2. **Shared Database Tenancy (ADR 0002):** A single shared PostgreSQL database utilizing row-level tenancy with `tenant_id` on all tenant-owned entities, mediated by Eloquent global scopes (`BelongsToTenant`) and a runtime `CurrentTenant` context.
3. **Backend Stack (ADR 0003):** Laravel 13 + Filament 5.6 for administrative, vendor, and support panels. Business logic lives in domain Actions and Support services, never in controllers or Filament resource definitions.
4. **Storefront Decoupling (ADR 0004):** Next.js 15 (React 19, TypeScript, Tailwind) is a decoupled customer storefront consuming the Laravel REST API (`/api/storefront/*`).
5. **Backend as Commercial Authority (ADR 0005, ADR 0006):**
   - The backend is the single source of truth for product prices, discounts, coupon calculations, shipping rates, totals, currency, payment states, and inventory availability.
   - The client/storefront is untrusted and must never send or dictate trusted totals (`subtotal`, `discount`, `shipping_fee`, `total`).
6. **Product Type & Variant Invariants (ADR 0013):**
   - Products are either `simple` or `variable` (enforced by `ProductType` enum).
   - Simple products have inventory tracked at the product level (`product_variant_id IS NULL`).
   - Variable products have inventory tracked at the variant level (`product_variant_id` NOT NULL).
   - Simple products cannot be purchased with a variant ID; variable products cannot be purchased without an active variant ID.
   - Every inventory movement must be recorded in an append-oriented `StockMovement` ledger.
7. **Algerian Localization & Geopolitical Realities:**
   - **Geography (ADR 0007, `docs/ALGERIA_GEOGRAPHY.md`):** Active dataset is strictly 58 wilayas and 1,541 communes. The 2026 territorial reform to 69 wilayas is explicitly deferred until commune-level mappings and shipping-rate migrations are fully established.
   - **Payments (ADR 0009):** Cash on Delivery (COD) and manual payment confirmation are first-class. Automated payment gateways (e.g. CIB/Edahabia/Satim) are deferred.
   - **Shipping (ADR 0010):** Internal shipping tables and manual workflows (home delivery vs stop-desk) precede third-party carrier API integrations (Yalidine, ZR Express, etc.).

---

## 2. Invariants & Security Boundaries (Expected System)

According to `docs/ai-first/SYSTEM_INVARIANTS.md` and `docs/ai-first/SECURITY_BOUNDARIES.md`:

| Invariant / Boundary | Documented Expectation | Enforcement Mechanism |
| :--- | :--- | :--- |
| **Tenant Isolation** | Every tenant-owned row must carry `tenant_id`. Cross-tenant queries are forbidden. | `BelongsToTenant` scope, `CurrentTenant` context, composite DB keys. |
| **Global Scope Removal** | Any use of `withoutGlobalScope('current_tenant')` must immediately be accompanied by an explicit `where('tenant_id', ...)` filter. | Code review, tests, query inspection. |
| **Store Resolution** | `Store` is a documented exception to the global tenant scope for public domain resolution, but queries must still enforce active/trial tenant status and fail-closed when tenant context is null. | `StoreReadinessChecker`, `TenantResolver`. |
| **Monetary Integrity** | Prices stored in integer minor units (DZD centimes). No client totals accepted during checkout. | Request validation, `CreateQuickOrder` action recalculations. |
| **Inventory Atomicity** | Reservation, order creation, payment creation, and stock movement must execute inside a database transaction with row-level locks. | DB transactions (`DB::transaction`), `StockMovement` observer/action. |
| **2FA Enforcement** | Mandatory 2FA for super admins, platform support, and vendor tenant owners. No bypass flags allowed. | Panel middleware (`RequireTwoFactorAuthentication`), Filament 2FA pages. |
| **Emergency 2FA Reset** | Command `security:reset-two-factor` must require target user and audit reason. Existing active sessions must be invalidated. | Console command, audit logger. *(Note: Docs acknowledge session revocation is incomplete).* |
| **Audit Immutability** | Audit logs (`AuditLog`) must be append-only and not editable from the admin UI. | Policy authorization, Filament resource absence/read-only restriction. |
| **Public API Data Leakage** | Public storefront API responses must not leak `tenant_id`, cost prices, or internal operational metadata. | Eloquent API resources (`StoreResource`, `ProductResource`). |

---

## 3. Technology & Architecture Status According to ADRs

### Accepted ADRs
- ADR 0001: Modular monolith
- ADR 0002: Shared database tenancy
- ADR 0003: Laravel Filament backend
- ADR 0004: Next.js storefront
- ADR 0005: Backend source of truth for money
- ADR 0006: Do not trust client totals
- ADR 0007: 69 wilayas not enabled now (58 wilayas active)
- ADR 0008: Marketplace deferred (single-store tenant model)
- ADR 0009: Manual payments first
- ADR 0010: Algerian shipping strategy (internal rates first)
- ADR 0013: Product variants and inventory design

### Proposed / Incomplete ADRs
- **ADR 0011 (Storefront Caching & Revalidation):** Proposed since 2026-05-07. Current storefront runs `force-dynamic` with `no-store` API calls.
- **ADR 0012 (Production Deployment Topology):** Proposed since 2026-05-27. Reverse proxy, queue supervision, and backup scripts are documented and tested on staging, but production monitoring, alerting, and rollback drills remain incomplete.
- **ADR 0014 (Error Tracking Provider):** Proposed since 2026-05-31. No provider (e.g. Sentry/Bugsnag) is integrated.
- **ADR 0015 (CORS Policy):** Proposed since 2026-05-31. Explicit origin allow-listing middleware is missing.
- **ADR 0016 (API Versioning Strategy):** Proposed since 2026-05-31. Storefront routes (`/api/storefront/*`) remain unversioned.

---

## 4. Documented Future Work & Known Gaps

1. **Active Session Revocation on 2FA Reset:** Explicitly acknowledged in `docs/ARCHITECTURE.md` (line 285) that `security:reset-two-factor` does not revoke active sessions.
2. **Incident Response Contacts:** `docs/operations/INCIDENT_RESPONSE.md` contains placeholder contacts marked TBD.
3. **Storefront Large-Catalog SEO:** Sitemaps consume pagination, but `sitemap-index.xml` for catalogs >50k products is not implemented.
4. **Historical vs Live Proof:** Staging smoke and backup evidence records (`docs/evidence/`) are dated (May 2026) and represent historical proof rather than continuous live health.
