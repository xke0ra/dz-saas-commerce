# Security Boundaries

Last updated: 2026-06-02

## Identity And Panel Access

- Admin panel access is for platform super admins.
- Support panel access is for super admins or platform support.
- Vendor panel access is for super admins or tenant users with required
  permissions.
- 2FA enforcement is part of panel security and must not be bypassed.

## Authorization

- Policies own permission decisions for models.
- Tenant permissions use stable permission strings from `TenantPermission`.
- Filament resources should call policies/actions rather than embedding hidden
  authorization logic.

## Public API

- Public API responses must not expose `tenant_id` or internal metadata without
  documented reason.
- Public storefront requests are untrusted.
- Host/domain resolution is not enough for sensitive mutation. Resolve the store
  and tenant explicitly.

## Money And Checkout

- Client totals are never trusted.
- Payment status is backend-owned.
- Coupon discounts are backend-calculated.
- Shipping fees are backend-calculated.

## Audit

- Sensitive admin and lifecycle operations should write audit entries.
- Audit logs must not be editable from admin UI.
- `docs/AUDIT_MATRIX.md` is the conservative source for current audit coverage.

## Secrets

- Secrets must not appear in docs, tests, examples, logs, or committed env files.
- Placeholder values should be clearly fake.
- Any real secret exposure requires incident handling.

## Operations

- Production debug mode is forbidden.
- Production app key must be present.
- Readiness failures must not be papered over to pass smoke checks.
- Evidence documents should record facts, not hide failures.
