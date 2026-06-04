# File Ownership Map

Last updated: 2026-06-02

Ownership means review responsibility, not exclusive edit permission.

| Path | Owner | Review Notes |
|---|---|---|
| `AI_CONTEXT.md` | AI systems/docs owner | Must stay concise but complete enough for new agents |
| `README.md` | Project maintainer | Should reflect high-level status only |
| `docs/README.md` | Documentation owner | Keep as canonical navigation |
| `docs/adr/*` | Architecture owner | Do not contradict without a new ADR |
| `docs/DOMAIN_CONTRACTS*` | Domain owner | Update with checkout/inventory/billing/tenancy changes |
| `docs/TENANCY_RULES.md` | Security/backend owner | Required for tenant-scope changes |
| `docs/SECURITY_BASELINE.md` | Security owner | Required for auth, audit, secrets, headers, proxy security |
| `docs/TESTING_STRATEGY.md` | QA/engineering owner | Update when test commands or coverage expectations change |
| `docs/PRODUCTION_READINESS.md` | Operations owner | Must keep proof boundaries explicit |
| `docs/evidence/*` | Operations/audit owner | Dated records; do not rewrite as runbooks |
| `docs/templates/*` | Operations owner | Blank templates only |
| `backend/app/Actions/*` | Backend domain owner | Business logic belongs here |
| `backend/app/Support/*` | Backend domain owner | Shared domain helpers and guards |
| `backend/app/Models/*` | Backend/data owner | Tenant/money/status changes require tests |
| `backend/app/Policies/*` | Security/backend owner | Permission changes require policy tests |
| `backend/app/Filament/*` | Backend UI owner | Keep callbacks thin |
| `backend/routes/api.php` | API/backend owner | Public API changes require docs and tests |
| `backend/routes/console.php` | Operations/backend owner | Commands require dry-run/safety docs when destructive |
| `backend/database/migrations/*` | Database owner | Must follow migration runbook |
| `storefront/src/lib/api.ts` | Frontend/API owner | API contract changes require backend docs |
| `storefront/src/lib/types.ts` | Frontend/API owner | Keep aligned with Laravel resources |
| `storefront/src/components/storefront/*` | Frontend owner | Checkout components require backend contract review |
| `deploy/*` | Operations owner | Deployment proof requires evidence docs |
| `.github/workflows/*` | CI owner | Do not change in docs-only tasks |

## Staging Rule

In a dirty worktree, stage only files owned by the current task. If prior
changes exist in the same file, either avoid committing or use carefully reviewed
patch staging.
