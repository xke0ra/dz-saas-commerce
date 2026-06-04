# Risk Registry

Last updated: 2026-06-02

| Risk | Severity | Evidence | Mitigation |
|---|---|---|---|
| Tenant data leak from missing tenant filter | Critical | Shared database tenancy and `withoutGlobalScope` usage | Enforce explicit `tenant_id` filters and tests |
| Checkout trusts client totals | Critical | ADR 0005/0006 and checkout docs | Backend-only calculations |
| Variant/simple product mismatch | High | `ProductType`, ADR 0013, checkout tests | Preserve product type rules |
| Inventory ledger drift | High | stock movement docs and actions | Use lifecycle actions only |
| 2FA bypass introduced as workaround | High | 2FA docs and security tests | Preserve enforcement, fix flow |
| Production readiness overclaimed | High | runbooks and evidence docs | Require dated proof and live smoke when current health matters |
| Monitoring gaps hidden by runbook presence | Medium | monitoring docs show missing metric sources | Track provider and metrics implementation |
| Incident contacts remain TBD | Medium | incident response doc | Fill before beta/production |
| Arabic/English doc drift | Medium | separate authoritative Arabic docs and summaries | Update both or mark summary status |
| API versioning assumed implemented | Medium | ADR 0016 exists, routes are unversioned | Do not claim versioned routes until implemented |
| Evidence docs edited as procedures | Medium | evidence/template separation | Keep evidence immutable and templates blank |
| Dirty worktree commits unrelated app changes | High | current status contains many pre-existing changes | Stage only task files, avoid broad commits |
| Zone.Identifier files committed | Low | untracked metadata files | Clean separately with approval |

## Risk Review Trigger

Open this file before any change touching checkout, tenancy, inventory, billing,
security, operations, CI, dependencies, or deploy scripts.
