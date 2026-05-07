# ADR 0002: Shared Database Tenancy

Date: 2026-05-07

Status: Accepted

## Context

The codebase uses shared database tenancy with `tenant_id`, `CurrentTenant`, tenant middleware/resolution, Eloquent scopes, policies, and database constraints. Tests cover tenant isolation in important flows.

## Decision

Use shared database tenancy with strict `tenant_id` boundaries.

## Consequences

- Tenant isolation is a security boundary.
- New tenant-owned tables must include tenant constraints where relevant.
- Any `withoutGlobalScope('current_tenant')` must be justified and accompanied by explicit `tenant_id` filtering or a clear platform admin context.
- Tenancy changes require tenant isolation tests.
