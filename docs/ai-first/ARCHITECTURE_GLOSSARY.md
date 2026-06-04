# Architecture Glossary

Last updated: 2026-06-02

| Term | Meaning |
|---|---|
| Agent | AI coding system such as Codex, Claude Code, Cursor agent, or OpenHands |
| AI-first docs | Documentation structured so agents can safely navigate, change, test, and explain the repo |
| AuditLog | Immutable business/security audit record |
| BelongsToTenant | Eloquent trait that applies the current tenant global scope and fills `tenant_id` on create |
| COD | Cash on delivery |
| CurrentTenant | Runtime tenant context service |
| Dated evidence | A proof document for a past operational event, not current live proof |
| Domain contract | Behavioral rule that code and docs must preserve |
| Feature gate | SaaS plan limit check before restricted operations |
| Filament panel | Laravel admin/vendor/support dashboard surface |
| Global tenant scope | `current_tenant` Eloquent scope used to isolate tenant rows |
| Idempotency key | Client-supplied checkout key used to replay or reject duplicate submissions |
| Manual payment proof | Operator-reviewed payment evidence, not an automated gateway |
| Modular monolith | Single codebase/process organized by domains rather than microservices |
| ProductType | Enum source of truth for `simple` and `variable` product behavior |
| Readiness gate | Domain validation that a store/product can be published |
| Readiness check | Runtime health readiness check for environment, DB, cache, queue, storage, Redis, search |
| Sellable unit | The exact inventory unit that can be sold: product-level for simple, variant-level for variable |
| StockMovement | Append-oriented inventory ledger event |
| Store | Tenant's public storefront entity and tenancy-sensitive public resolution point |
| Storefront | Next.js public customer surface |
| Tenant | SaaS merchant/account boundary |
| Tenant isolation | Rule that one tenant's data must not be visible or mutable by another tenant |
| Variable product | Product purchased through one of its active variants |

## Normalized Phrases

- Use "dated staging proof" instead of "staging is currently live" unless a
  fresh smoke was run.
- Use "production readiness pending" instead of "production ready" until all
  production gates have evidence.
- Use "domain readiness" for `StoreReadinessChecker`, not "deployment readiness".
- Use "backend source of truth" for money, totals, shipping, discounts,
  inventory, and subscription limits.
