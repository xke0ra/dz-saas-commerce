# DZ SaaS Commerce AI Operating Manual

Last updated: 2026-06-02

This file is the first document an AI coding agent must read before changing
`dz-saas-commerce`.

## Repository Overview

`dz-saas-commerce` is a monorepo for a multi-tenant Algerian SaaS commerce
platform.

- `backend/`: Laravel 13, PHP 8.3+, Filament 5.6 panels, PostgreSQL, Redis,
  Scout/Meilisearch, queues, scheduler, health checks, domain actions.
- `storefront/`: Next.js 15.5, React 19, TypeScript, Tailwind, customer
  storefront, cart, checkout, order tracking, SEO, and theme rendering.
- `docs/`: canonical architecture, domain, security, testing, operations, ADR,
  evidence, and AI-first documentation.
- `deploy/`: staging, backup, reverse proxy, and supervision examples.

## Start Here

Read these before coding:

1. `docs/README.md`
2. `docs/ai-first/README.md`
3. `docs/ARCHITECTURE.md`
4. `docs/DOMAIN_CONTRACTS_SUMMARY.md`
5. `docs/TENANCY_RULES.md`
6. `docs/SECURITY_BASELINE.md`
7. `docs/TESTING_STRATEGY.md`
8. relevant ADRs in `docs/adr/`

Arabic-authoritative documents, especially `docs/DOMAIN_CONTRACTS_AR.md`, may
contain the authoritative wording for domain behavior. Do not change covered
behavior without checking them.

## Architectural Principles

- Modular monolith first. Do not split services without a new ADR.
- Backend is the source of truth for money, totals, discounts, shipping fees,
  payment status, inventory reservation, and subscription limits.
- Storefront is a public UX surface. It must not become a commerce authority.
- Business logic belongs in `backend/app/Actions`, `backend/app/Support`,
  data objects, policies, and small observer side effects.
- Controllers, Filament resource callbacks, and Next.js components should stay
  thin.
- ADRs are binding until superseded by a new ADR.

## Critical Invariants

- Never break tenant isolation.
- Every tenant-owned commercial row must preserve `tenant_id`.
- Every `withoutGlobalScope('current_tenant')` needs an explicit tenant filter
  in the same query path.
- `Store` is a documented public-resolution exception, but store queries remain
  tenant-isolation sensitive.
- Never trust client totals.
- `ProductType` is the source of truth for simple vs variable products.
- Simple products are purchased without `product_variant_id`.
- Variable products require `product_variant_id`.
- Inventory is tracked by sellable unit: product-level for simple products,
  variant-level for variable products.
- Inventory lifecycle changes must write `StockMovement`.
- Audit-sensitive operations must preserve auditability.
- 2FA enforcement must not be bypassed as a fix.
- Production readiness must not be claimed without current evidence.
- Dated evidence documents are not current live proof.

See `docs/ai-first/SYSTEM_INVARIANTS.md` for the full invariant table.

## Domain Map

High-risk backend domains:

- Tenancy: `backend/app/Support/Tenancy`, tenant models, policies, middleware.
- Checkout: `CreateQuickOrder`, checkout request/data/support, storefront cart.
- Variants: `ProductType`, product variant models, variant migrations, ADR 0013.
- Inventory: inventory actions, `InventoryItem`, `StockMovement`.
- Billing: subscription lifecycle actions, feature gates, invoices, payments.
- Security: 2FA support, panel middleware, audit logger, policies.
- Operations: health checker, deploy scripts, staging/backup/runbooks.

See `docs/ai-first/DOMAIN_MAP.md` and
`docs/ai-first/DOMAIN_DEPENDENCY_MAP.md`.

## Forbidden Actions

Do not:

- disable 2FA to solve an access bug
- add a bypass flag for 2FA or authorization enforcement
- move trusted money or inventory logic to the storefront
- remove tenant filters to make a query pass
- add migrations, dependencies, CI changes, or app code during docs-only work
- stage or commit unrelated dirty worktree changes
- edit dated evidence records as if they were runbooks
- claim current staging or production health without a fresh smoke proof

## Safe Development Workflow

1. Run `git status --short --branch`.
2. Identify existing user changes before editing.
3. Read the relevant docs and ADRs.
4. Inspect current source files.
5. For high-risk changes, state the current flow and intended integration point.
6. Make the narrowest change.
7. Run targeted tests.
8. Update docs when behavior, contracts, evidence, or verification changes.
9. Report exact commands and results.

## Required Tests By Change Type

Use `docs/ai-first/TESTING_REQUIREMENTS.md` and
`docs/TESTING_STRATEGY.md`.

Examples:

- Docs-only: `git diff --check`, docs-only diff guard, secret grep when needed.
- Checkout: backend quick checkout, idempotency, and variant inventory tests as
  relevant.
- Tenancy: tenant foundation, integrity, permission, and affected feature tests.
- Security/2FA: security headers, trusted proxy, 2FA, reset command, audit tests.
- Storefront: typecheck/build/e2e when behavior, routes, checkout, SEO, or types
  change.
- Operations: script syntax checks, local/ephemeral smoke, or external staging
  smoke only when in scope.

## High-Risk Areas

- `backend/app/Actions/Checkout/CreateQuickOrder.php`
- `backend/app/Support/Checkout/*`
- `backend/app/Support/Tenancy/*`
- `backend/app/Models/Concerns/BelongsToTenant.php`
- `backend/app/Support/Readiness/StoreReadinessChecker.php`
- `backend/routes/console.php`
- `backend/app/Filament/Pages/TwoFactor*`
- variant and inventory migrations
- `storefront/src/lib/types.ts`
- `storefront/src/lib/api.ts`
- storefront checkout/cart components
- `deploy/staging/*`
- `.github/workflows/*`

## Review Checklist

Before finishing:

- Did the change stay inside the requested scope?
- Did it preserve all critical invariants?
- Did it avoid unrelated refactors?
- Did it avoid touching user changes that were already present?
- Did it update docs when behavior changed?
- Did verification run, and are exact results reported?
- If verification did not run, is the reason explicit?

## Deployment Checklist

For deployment-related tasks:

- Read `docs/PRODUCTION_READINESS.md`.
- Read `docs/STAGING_DEPLOYMENT_RUNBOOK_AR.md`.
- Read `deploy/staging/README.md`.
- Read `deploy/staging/GITHUB_ENVIRONMENT.md`.
- Keep runbooks, templates, and evidence separate.
- Record new external proof under `docs/evidence/` only after a real smoke.

## Security Checklist

- Read `docs/SECURITY_BASELINE.md`.
- Read `docs/TENANCY_RULES.md`.
- Read `docs/AUDIT_MATRIX.md`.
- Preserve 2FA enforcement.
- Preserve tenant filters.
- Avoid raw PII in logs.
- Avoid real secrets in docs, examples, tests, or env files.
- Keep emergency commands target-specific, reason-required, and dry-run aware.

## Documentation Map

- Navigation: `docs/README.md`
- AI layer: `docs/ai-first/`
- Architecture: `docs/ARCHITECTURE.md`
- ADRs: `docs/adr/`
- Domain contracts: `docs/DOMAIN_CONTRACTS_SUMMARY.md`,
  `docs/DOMAIN_CONTRACTS_AR.md`
- Tenancy: `docs/TENANCY_RULES.md`
- Security: `docs/SECURITY_BASELINE.md`
- Testing: `docs/TESTING_STRATEGY.md`
- Operations: `docs/PRODUCTION_READINESS.md`, runbooks, templates, evidence

## Agent Workflow

For autonomous work, use this order:

1. Orient from `AI_CONTEXT.md`.
2. Read the AI-first doc matching the task.
3. Read canonical domain docs and ADRs.
4. Inspect source.
5. Edit narrowly.
6. Verify.
7. Summarize files changed and verification.

If the user says docs-only, do not touch application code, CI, migrations,
dependencies, or deploy scripts unless explicitly allowed.

## Current Proof Boundary

Repository docs contain dated evidence for:

- external staging smoke on 2026-05-26
- staging PostgreSQL backup automation on 2026-05-28
- staging restore drill on 2026-05-28

These are historical proof records. They do not prove current external uptime or
production readiness without a fresh smoke and updated evidence.
