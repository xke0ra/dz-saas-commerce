# Onboarding Guide — New Developer

Last updated: 2026-05-31

Welcome to `dz-saas-commerce`. This guide gets you productive in the first 2 hours.

---

## What Is This Project?

A multi-tenant SaaS e-commerce platform for the Algerian market. Merchants sign up, get a store, manage products, and receive cash-on-delivery orders. You are building the platform that powers those stores.

- **Backend:** Laravel 13 + PHP 8.3 + Filament 5.6 (`backend/`)
- **Storefront:** Next.js 15 + React 19 + TypeScript (`storefront/`)
- **Database:** PostgreSQL (shared, row-level tenant isolation)
- **Panels:** Admin (`/admin`), Vendor (`/vendor`), Support (`/support`)

---

## Hour 1 — Read Before Touching Anything

### Read in this order (30 minutes)

1. **`docs/ARCHITECTURE.md`** — system shape, domains, tech stack, the change rule
2. **`docs/adr/README.md`** — the 16 architecture decisions governing this project
3. **`docs/TENANCY_RULES.md`** — tenant isolation rules (CRITICAL — read every word)
4. **`docs/DOMAIN_CONTRACTS_SUMMARY.md`** — English summary of behavioral contracts for checkout, inventory, billing, security

### Key rules you must internalize

- **Never remove `withoutGlobalScope('current_tenant')` without adding `->where('tenant_id', $tenantId)`.**
- **Backend calculates all prices, totals, and inventory.** Storefront never sends trusted money values.
- **`simple` products → no `product_variant_id`.** **`variable` products → must have `product_variant_id`.**
- **Business logic goes in `app/Actions/{Domain}/`, not controllers.**
- **Any architectural change requires an ADR.**

---

## Hour 1 — Local Setup (30 minutes)

Follow [`docs/LOCAL_DEVELOPMENT.md`](LOCAL_DEVELOPMENT.md). Quick version:

```bash
# Prerequisites: PHP 8.3, Composer, Node.js compatible with Next.js 15, pnpm 11.1.2, Docker

# 1. Start services
docker compose up -d postgres redis meilisearch minio mailpit

# 2. Backend setup
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed

# 3. Verify backend
php artisan system:health --scope=ready --format=json   # all checks green?
php artisan test                                          # 292 passed expected

# 4. Storefront setup (separate terminal)
cd storefront
pnpm install
cp .env.local.example .env.local
pnpm dev

# 5. Open
# Storefront:     http://localhost:3000
# Admin panel:    http://localhost:8000/admin
# Vendor panel:   http://localhost:8000/vendor
# Mailpit:        http://localhost:8025
```

---

## Hour 2 — Understand the Domain You'll Work On

### Backend Structure

```
backend/app/
├── Actions/{Domain}/    # Read the Action for your domain before editing
├── Models/              # Eloquent models — tenant-scoped via BelongsToTenant
├── Policies/            # Authorization — 30 policies, 55 permissions
├── Filament/            # Admin/Vendor/Support panel UI
└── Http/Controllers/    # Thin — 4 controllers total
```

### Read Before Editing

| Domain | Read these first |
|--------|-----------------|
| **Checkout** | `docs/STOREFRONT_CART.md`, `docs/DOMAIN_CONTRACTS_SUMMARY.md §2`, `docs/adr/0005-*.md`, `docs/adr/0006-*.md` |
| **Inventory / Stock** | `docs/DOMAIN_CONTRACTS_SUMMARY.md §4-5`, `docs/AUDIT_MATRIX.md` |
| **Billing** | `docs/DOMAIN_CONTRACTS_SUMMARY.md §6`, `docs/SECURITY_BASELINE.md` |
| **Tenancy / RBAC** | `docs/TENANCY_RULES.md`, `docs/adr/0002-*.md` |
| **Product Variants** | `docs/DOMAIN_CONTRACTS_SUMMARY.md §3`, `docs/adr/0013-*.md` |
| **Storefront UI** | `docs/STOREFRONT_CART.md`, `docs/STOREFRONT_SEO.md`, `docs/STOREFRONT_THEME.md` |
| **Security** | `docs/SECURITY_BASELINE.md`, `docs/TENANCY_RULES.md`, `docs/AUDIT_MATRIX.md` |
| **Deployment** | `docs/PRODUCTION_READINESS.md`, `docs/adr/0012-*.md` |

### Run Tests Before Making Changes

```bash
cd backend
php artisan test                        # full suite
php artisan test --filter=YourDomain    # targeted
```

Never skip tests for checkout, inventory, billing, or tenancy changes.

---

## Key Concepts Explained

### Why does money look like `price_minor`?

All money values are stored as integer "minor units" (e.g., centimes for DZD). `100_00` = 100 DZD. No floating-point rounding bugs. See ADR 0005.

### What is `BelongsToTenant`?

A trait applied to all tenant-owned Eloquent models. It adds a global scope that automatically filters every query to the current tenant. Without this, one tenant could read another tenant's data.

```php
// This automatically adds WHERE tenant_id = {current_tenant_id}
Product::where('status', 'active')->get();

// ONLY remove the scope if you have a guard — THEN add it back manually:
Product::withoutGlobalScope('current_tenant')
    ->where('tenant_id', $explicitTenantId)  // ← required
    ->where('status', 'active')
    ->get();
```

### What is an Action?

```php
// ✅ Correct: business logic in Action
class CreateQuickOrder
{
    public function handle(Store $store, QuickOrderData $data): Order { ... }
}

// ❌ Wrong: business logic in controller
class StorefrontController
{
    public function checkout(Request $request) {
        // 400 lines of logic here — don't do this
    }
}
```

### What is `CurrentTenant`?

A scoped service container binding that holds the currently authenticated tenant for the duration of a request. Set by `ResolveTenantFromRequest` middleware. Never read it from models — that's what the global scope does automatically.

---

## Development Workflow

See [`docs/DEVELOPMENT_WORKFLOW.md`](DEVELOPMENT_WORKFLOW.md) for the full process. Key points:

1. Inspect before changing: `rg -n "ClassOrConcept" backend/app backend/tests`
2. Check git status: `git status --short`
3. Make a narrow, focused change
4. Add or update tests (required for checkout/inventory/billing/tenancy)
5. Run `php artisan test`
6. Run `./vendor/bin/pint` (code style)
7. Update relevant docs in the same commit
8. Never use `skip()` or `todo()` to hide failing tests

---

## CI Gates You Must Pass

All 5 quality gates in `.github/workflows/quality.yml` must be green:

```bash
# What CI runs — verify locally before pushing:
cd backend

composer validate --strict
composer audit
./vendor/bin/pint --test              # code style check
php artisan migrate --force
php artisan system:health --scope=ready
php artisan test
php artisan route:list --json

cd ../storefront
pnpm install --frozen-lockfile
pnpm audit
pnpm typecheck
pnpm build
```

---

## First Things to Ask Your Team

1. What is the current staging URL and how do I get access?
2. Is there an on-call or incident channel I should join?
3. Which domain/feature am I starting with?
4. Are there any open PRs I should review first?

---

## Where To Find Things

| I need to... | Look here |
|-------------|-----------|
| Understand the overall system | `docs/ARCHITECTURE.md` |
| Check an architecture decision | `docs/adr/README.md` |
| Find security rules | `docs/SECURITY_BASELINE.md` |
| Find tenant isolation rules | `docs/TENANCY_RULES.md` |
| Find domain behavioral contracts | `docs/DOMAIN_CONTRACTS_SUMMARY.md` |
| Find checkout rules | `docs/STOREFRONT_CART.md` |
| Set up locally | `docs/LOCAL_DEVELOPMENT.md` |
| Run tests | `docs/TESTING_STRATEGY.md` |
| Understand production readiness | `docs/PRODUCTION_READINESS.md` |
| Find what's been proven in staging | `docs/evidence/` |
| Handle an incident | `docs/operations/INCIDENT_RESPONSE.md` |
| Find which audit events exist | `docs/AUDIT_MATRIX.md` |
