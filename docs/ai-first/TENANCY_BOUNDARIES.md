# Tenancy Boundaries

Last updated: 2026-06-02

## Boundary Definition

A tenant is the SaaS merchant/account boundary. Most commerce data is tenant
owned and must be isolated by `tenant_id`.

## Tenant-Owned Domains

- stores and store settings
- categories and products
- variants and product option values
- inventory and stock movements
- customers
- orders and order items
- payments and payment methods
- shipping rates, companies, shipments
- returns
- subscriptions, invoices, subscription payments, usage counters
- domains
- coupons and coupon redemptions
- support tickets when tenant-scoped

## Boundary Mechanisms

- `BelongsToTenant` global scope.
- `CurrentTenant` runtime context.
- explicit `tenant_id` filtering after scope removal.
- policies that check tenant permissions.
- composite constraints where needed.
- tests that assert cross-tenant rejection.

## Documented Exceptions

`Store` is a tenancy-sensitive public resolution entity. It may be queried
outside the current tenant context for public host resolution, but that does not
make it safe to use casually. Any store query in mutation or private context must
be reviewed for tenant isolation.

## Forbidden Patterns

- `withoutGlobalScope('current_tenant')` without an immediate `tenant_id` filter.
- inferring tenant from untrusted client payload.
- exposing internal tenant ids through public storefront responses.
- using host header alone for sensitive operations.
- creating cross-tenant relationships between commercial entities.

## Review Checklist

- Does every affected table have `tenant_id` where required?
- Does every cross-table lookup preserve tenant equality?
- Does every policy use the correct tenant permission?
- Does every public API response hide tenant internals?
- Do tests include negative cross-tenant cases?
