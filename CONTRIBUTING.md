# Contributing

This document defines the contribution process for `dz-saas-commerce`.

---

## Before You Start

Read these documents in order:

1. `docs/ARCHITECTURE.md` — system shape, domains, the change rule
2. `docs/adr/README.md` — the 16 architecture decisions governing this project
3. `docs/TENANCY_RULES.md` — tenant isolation rules (read every word before touching any query)
4. `docs/DOMAIN_CONTRACTS_SUMMARY.md` — behavioral contracts for checkout, inventory, billing, security
5. `docs/DEVELOPMENT_WORKFLOW.md` — working process and commit-ready checklist

New to the project? Start with `docs/guides/ONBOARDING.md`.

---

## What You Need

- PHP 8.3+, Composer
- Node.js compatible with Next.js 15, pnpm 11.1.2
- Docker and Docker Compose
- See `docs/LOCAL_DEVELOPMENT.md` for full setup

---

## Working Rules

### Do not change these without an ADR

- Module boundaries
- Tenancy behavior or global scope logic
- Authorization model
- API contracts (request/response shape)
- Money calculation logic
- Storage, search, or queue behavior

### Before making any change

```bash
# Inspect the relevant code first
rg -n "ClassOrConcept" backend/app backend/database backend/routes backend/tests

# Check working tree state
git status --short
```

### Business logic placement

- ✅ `app/Actions/{Domain}/` — all business logic lives here
- ✅ `app/Support/` — domain support classes
- ❌ Never in controllers, Filament resources, or Next.js components

### Tenant isolation

Every query on a tenant-owned model is automatically scoped via `BelongsToTenant`. If you use `withoutGlobalScope('current_tenant')`, you must add `->where('tenant_id', $tenantId)` immediately. No exceptions. See `docs/TENANCY_RULES.md`.

### Money

All money fields end in `_minor` (integer centimes). Never use floats for money. See ADR 0005.

---

## Running Tests

```bash
# Full backend suite (must stay at 292+ passed)
cd backend && php artisan test

# Storefront (Docker path — preferred)
./storefront/scripts/verify-docker.sh all

# Storefront (native, sequential — not concurrent)
cd storefront && pnpm build && pnpm typecheck && pnpm test:e2e
```

Checkout, inventory, billing, and tenancy changes **must** have tests. See `docs/TESTING_STRATEGY.md`.

---

## Code Style

```bash
# PHP — run after any PHP change
cd backend && ./vendor/bin/pint

# TypeScript — checked during build/typecheck
cd storefront && pnpm typecheck
```

---

## CI Gates

All five must be green before merging:

| Gate | What it checks |
|------|---------------|
| `Repository Hygiene` | No secrets, no generated artifacts committed |
| `Backend` | composer audit, Pint, migrate, health check, full test suite |
| `Storefront` | pnpm audit, typecheck, build |
| `Dockerfile Checks` | buildx lint, build smoke, Trivy scan |
| `Storefront E2E` | Playwright Chromium tests |

---

## Commit Messages

Use the conventional format:

```
type(scope): short description

- longer explanation if needed
```

Types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `security`

Examples:
```
feat(checkout): add coupon validation before inventory reservation
fix(tenancy): add missing tenant_id filter after withoutGlobalScope
docs: update PRODUCTION_READINESS.md with monitoring status
test(billing): add suspension behavior regression test
```

---

## Documentation Rule

Any feature, architecture change, or modification to sensitive behavior must update the relevant documentation in the same commit. See `docs/DEVELOPMENT_WORKFLOW.md` — Documentation Update Rule.

---

## Security Issues

Do **not** open a public GitHub issue for security vulnerabilities. See `SECURITY.md`.

---

## Reporting Bugs

Open a GitHub issue with:
- Exact steps to reproduce
- Expected vs actual behavior
- Environment (local/staging)
- Relevant log output (redact any PII)

Do not include secrets, customer data, or internal URLs in issues.
