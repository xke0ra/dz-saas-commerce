# dz-saas-commerce — Backend

Laravel 13 backend for a multi-tenant Algerian SaaS commerce platform.

> Full project documentation lives in [`docs/`](../docs/README.md). This file is a quick entry point for backend work only.

---

## Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13, PHP 8.3 |
| Admin Panels | Filament 5.6 (admin / vendor / support) |
| Database | PostgreSQL 16 |
| Cache / Sessions / Queue | Redis |
| Search | Meilisearch (via Laravel Scout) |
| Object Storage | S3-compatible (MinIO locally) |
| Auth | Filament-native + mandatory TOTP 2FA |

---

## Quick Start (Local)

See [`docs/LOCAL_DEVELOPMENT.md`](../docs/LOCAL_DEVELOPMENT.md) for the full setup contract.

```bash
# 1. Start services
docker compose up -d postgres redis meilisearch minio mailpit

# 2. Install dependencies
cd backend
composer install

# 3. Prepare environment
cp .env.example .env
php artisan key:generate

# 4. Migrate and seed
php artisan migrate
php artisan db:seed

# 5. Run tests
php artisan test

# 6. Start queue worker (separate terminal)
php artisan queue:work redis --tries=3 --timeout=90 --sleep=3

# 7. Start scheduler (separate terminal)
php artisan schedule:work
```

All credentials in `.env.example` and `docker-compose.yml` are local dummy values. Do not use them outside local development.

---

## Project Structure

```
backend/app/
├── Actions/          # Business operations (46 files) — core domain logic
│   ├── Billing/      # Subscription lifecycle
│   ├── Checkout/     # Order creation, idempotency, abuse guard
│   ├── Inventory/    # Stock movements, settlements
│   ├── Orders/       # Status transitions
│   ├── Shipping/     # Shipment lifecycle
│   └── ...           # Returns, Coupons, Domains, Support, Tenancy
├── Data/             # Typed DTOs
├── Enums/            # Domain constants (27 files)
├── Filament/         # Admin / Vendor / Support panels (227 files)
├── Http/             # Thin controllers (4), middleware, requests, resources
├── Jobs/             # Background jobs
├── Models/           # Eloquent models (44 files)
├── Observers/        # Side effects and audit hooks
├── Policies/         # Authorization (30 files, 55 permissions)
└── Support/          # Domain support classes
```

**Pattern:** Thin controllers — all business logic in `app/Actions/{Domain}/`.

---

## Key Domains

| Domain | Entry Action | Notes |
|--------|-------------|-------|
| Checkout | `CreateQuickOrder` | Idempotency + abuse guard + inventory lock |
| Billing | `ProcessBillingLifecycle` | Grace periods, renewal, suspension |
| Inventory | `AdjustInventoryManually`, `SettleOrderInventory` | Append-only ledger |
| Orders | `TransitionOrderStatus` | State machine with allowed transitions |
| Shipping | `TransitionShipmentStatus` | Wilaya/commune-based rates |
| Tenancy | `InviteUserToTenant` | RBAC with 55 granular permissions |
| Catalog | `SearchStorefrontProducts` | Meilisearch via Scout |

---

## Tenant Isolation

Three-layer isolation — **read [`docs/TENANCY_RULES.md`](../docs/TENANCY_RULES.md) before touching any tenant-scoped code:**

1. **Application:** `BelongsToTenant` Eloquent global scope on all tenant models
2. **Middleware:** `ResolveTenantFromRequest` with `try/finally` cleanup
3. **Database:** Composite FK constraints (e.g., `orders(tenant_id, store_id) → stores(tenant_id, id)`)

Rule: every use of `withoutGlobalScope('current_tenant')` **must** add `->where('tenant_id', $tenantId)`.

---

## Security

- Mandatory TOTP 2FA for: `super_admin`, `platform_support`, `tenant_owner`
- Recovery codes: encrypted `array` cast in DB
- Emergency reset: `php artisan security:reset-two-factor {user}`
- All financial CHECK constraints at DB level (`total = subtotal + tax`, `paid <= total`)
- Money stored as integer minor units (no floating-point)

See [`docs/SECURITY_BASELINE.md`](../docs/SECURITY_BASELINE.md).

---

## Health Checks

```bash
# Liveness (process alive)
php artisan system:health --scope=live --format=json
curl http://localhost/api/system/health/live

# Readiness (all dependencies healthy)
php artisan system:health --scope=ready --format=json
curl http://localhost/api/system/health/ready
```

Readiness checks: PostgreSQL, Redis, queue backend, storage disk, Meilisearch. Fails if `APP_DEBUG=true` or `APP_KEY` is missing.

---

## Tests

```bash
php artisan test                          # Full suite
php artisan test --filter=QuickCheckout   # Single test class
php artisan test --parallel               # Parallel (requires ParaTest)
```

Current baseline: **292 passed, 1448 assertions** (after commit `045c264`).

Coverage areas: checkout, billing lifecycle, tenant isolation, security headers, 2FA, inventory ledger, product variants, order fulfillment, payment workflow.

See [`docs/TESTING_STRATEGY.md`](../docs/TESTING_STRATEGY.md) for the full strategy and required coverage rules.

---

## CI

Five quality gates in `.github/workflows/quality.yml`:

1. **Repository hygiene** — secret scan, clean export check
2. **Backend** — `composer validate` → `composer audit` → Pint → migrate → readiness → test → `route:list`
3. **Storefront** — `pnpm audit` → typecheck → build
4. **Dockerfile checks** — buildx lint + build smoke + Trivy vulnerability scan
5. **Storefront E2E** — Playwright Chromium tests

---

## Key Read-Before-Edit Rules

| Changing... | Read first |
|-------------|-----------|
| Checkout / order creation | `docs/STOREFRONT_CART.md`, `docs/DOMAIN_CONTRACTS_SUMMARY.md`, `docs/adr/0005-*.md`, `docs/adr/0006-*.md` |
| Inventory / stock movements | `docs/DOMAIN_CONTRACTS_SUMMARY.md`, `docs/AUDIT_MATRIX.md`, `docs/TENANCY_RULES.md` |
| Tenancy / `withoutGlobalScope` | `docs/TENANCY_RULES.md`, `docs/adr/0002-*.md` |
| Billing / subscriptions | `docs/DOMAIN_CONTRACTS_SUMMARY.md`, `docs/SECURITY_BASELINE.md` |
| Product variants | `docs/DOMAIN_CONTRACTS_SUMMARY.md`, `docs/adr/0013-*.md` |
| Architecture | `docs/ARCHITECTURE.md`, `docs/adr/` |
| Security | `docs/SECURITY_BASELINE.md`, `docs/TENANCY_RULES.md` |
| Deployment | `docs/PRODUCTION_READINESS.md`, `docs/adr/0012-*.md` |

---

## Reporting Security Vulnerabilities

Do **not** open a public GitHub issue. See [`SECURITY.md`](../SECURITY.md) at the project root.
