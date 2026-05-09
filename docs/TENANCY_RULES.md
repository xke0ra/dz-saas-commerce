# Tenancy Rules

Last updated: 2026-05-09

This document is the operating contract for tenant isolation.

## Strategy

The platform uses a shared PostgreSQL database with `tenant_id` on tenant-owned business tables.

The current tenant is represented by:

- `App\Support\Tenancy\CurrentTenant`
- `App\Support\Tenancy\TenantResolver`
- `App\Http\Middleware\ResolveTenantFromRequest`
- `App\Models\Concerns\BelongsToTenant`

The tenant context is registered as scoped in `App\Providers\AppServiceProvider`.

## Resolution Sources

`TenantResolver::resolveFromRequest()` resolves tenant context in this order:

1. Host-based resolution through `resolveFromHost()`
2. Authenticated user context through `X-Tenant-ID` header or `tenant_id` query parameter
3. Persisted vendor session tenant from `TenantSwitcher::SESSION_KEY`
4. First tenant membership for the authenticated user

Host-based store resolution supports:

- active verified custom domains from `domains.hostname`
- `stores.domain`
- `stores.subdomain`

Current limitations:

- localhost and raw IP hosts do not produce a subdomain tenant context. Local development can use fallback identifiers in the storefront or explicit tenant context for authenticated vendor requests.
- `stores.domain` remains a legacy fallback while the domains table is adopted. Production custom-domain routing should prefer active, verified rows from `domains.hostname`.

## Current Tenant Lifecycle

`ResolveTenantFromRequest` sets the current tenant before the request continues and always clears it in `finally`.

The vendor Filament panel uses this middleware in `authMiddleware`.

Important implication: any tenant-owned query inside `/vendor` should see the current tenant after authentication and tenant resolution.

## Tenant-Owned Models

Tenant-owned models should use `App\Models\Concerns\BelongsToTenant` unless there is a documented reason not to.

The trait:

- adds the `current_tenant` global scope
- writes `tenant_id` during create when current tenant exists
- exposes `scopeForTenant()`
- defines `tenant()` relation

Known tenant-owned areas include:

- catalog
- inventory
- customers
- orders
- payments
- shipping
- returns
- billing records
- domains
- support tickets
- store settings
- theme settings
- staff memberships
- audit logs where relevant

Known exception: `App\Models\Store` is tenant-owned but does not use `BelongsToTenant` because storefront host/domain resolution and platform-level workflows need controlled cross-tenant lookup. Existing code relies on explicit `scopeForTenant()` calls, policies, and relationship constraints. `Store::forTenant(null)` fails closed and returns no rows. New store queries must be checked for explicit tenant filtering, public storefront availability checks, or platform-admin authorization.

## Queries

Default rule:

Every tenant-owned query must be scoped to a tenant.

Allowed scoping forms:

- current tenant global scope through `BelongsToTenant`
- explicit `where('tenant_id', $tenantId)`
- `scopeForTenant($tenant)`
- relationship that is already constrained by tenant
- database-enforced composite tenant foreign key

## `withoutGlobalScope('current_tenant')`

Using `withoutGlobalScope('current_tenant')` is allowed only when followed by an explicit tenant guard or when the data is intentionally platform-wide.

Allowed examples:

- public storefront endpoints that resolve a store and then query by `$store->tenant_id`
- checkout action that uses `$store->tenant_id` and wraps money/inventory operations in a transaction
- support/admin workflows where platform users need cross-tenant visibility
- jobs that run without HTTP tenant context but load records by explicit tenant
- tests that intentionally assert tenant isolation

Required pattern:

```php
Model::query()
    ->withoutGlobalScope('current_tenant')
    ->where('tenant_id', $tenantId);
```

Not allowed:

```php
Model::query()
    ->withoutGlobalScope('current_tenant')
    ->get();
```

Exception: platform-wide admin/reporting queries must be documented in the class or method and protected by policy.

## Database Integrity

The migration `2026_04_25_000000_add_tenant_integrity_constraints.php` adds composite constraints for important cross-tenant relationships.

This is the correct direction. New tenant-owned cross-record relations should add similar protection where PostgreSQL supports it cleanly.

Required for new tenant-owned tables:

- `tenant_id` column
- foreign key to `tenants`
- index matching expected tenant-scoped queries
- composite uniqueness where slugs/numbers are tenant-specific
- composite foreign keys for same-tenant child relationships when practical

## Filament Vendor Resources

Vendor resources must:

- use policies
- scope lists to `CurrentTenant`
- assign current tenant on create/update
- avoid exposing `tenant_id` as an editable field to normal vendor users
- avoid cross-tenant `Select` options

Current helper traits:

- `App\Filament\Vendor\Concerns\ScopesToCurrentTenant`
- `App\Filament\Vendor\Concerns\AssignsCurrentTenant`

Use these traits or an equivalent explicit implementation for all new vendor tenant-owned resources.

## RBAC

Platform roles:

- `super_admin`
- `platform_support`

Tenant roles:

- `tenant_owner`
- `store_admin`
- `store_staff`

Tenant permissions live in `App\Enums\TenantPermission`.

Rules:

- Super admin can access platform admin and vendor panel.
- Platform support can access support panel.
- Tenant users can access vendor panel only when they have `stores.view` for the resolved tenant.
- Tenant permission overrides on the pivot may explicitly allow or deny permissions.

## Storefront Tenancy

The storefront resolves store context by host first and by configured fallback store identifier second.

Backend public storefront endpoints resolve a store by id, subdomain, domain, or slug and then scope public data by that store's `tenant_id`.

Public storefront resources must avoid exposing internal tenant identifiers unless a concrete public consumer is documented. The current public `StoreResource` omits `tenant_id`.

Storefront endpoints must hide unavailable stores by returning 404 when:

- store is not active
- tenant is not active or trial

## Tenant Switcher

The vendor panel now has a first tenant switcher implementation:

- support class: `App\Support\Tenancy\TenantSwitcher`
- page: `App\Filament\Vendor\Pages\SwitchTenant`
- page path: `/vendor/switch-tenant`
- POST route: `vendor.tenants.switch`
- controller: `App\Http\Controllers\Vendor\SwitchTenantController`
- session key: `dz_saas_commerce.vendor_tenant_id`

Current behavior:

- vendors can select only tenants they belong to
- super admins can select any tenant
- the selected tenant is persisted in session
- `TenantResolver` reads the persisted tenant for authenticated vendor requests
- the switcher page shows only available tenants for the authenticated account

Future hardening:

- add a topbar/user-menu shortcut if the page is not discoverable enough
- add search for super admins with many tenants
- clear stale session tenant when membership is removed
- show richer tenant/store status metadata in the switcher
- consider Filament native tenancy only if it can be introduced without breaking current routes

## Testing Requirements

Every new tenant-owned domain must test:

- current tenant sees its own records
- current tenant does not see another tenant's records
- vendor cannot access another tenant's records
- super admin behavior where platform-wide access is expected
- public endpoints always scope by resolved store tenant

## Review Checklist

Before merging tenant-related work:

1. Does every tenant-owned table have `tenant_id`?
2. Does every tenant-owned model have tenant scoping or documented exception?
3. Does every use of `withoutGlobalScope('current_tenant')` add an explicit tenant condition?
4. Are Filament select options tenant-scoped?
5. Are policies registered in `AppServiceProvider`?
6. Are cross-tenant relationships protected by DB constraints where practical?
7. Are tests covering both allowed and denied tenant access?
