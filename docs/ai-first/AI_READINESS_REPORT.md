# AI Readiness Report

Last updated: 2026-06-02

## Scores

| Category | Score | Rationale |
|---|---:|---|
| Human documentation score | 4.1 / 5 | Strong docs index, ADRs, domain contracts, runbooks, and testing strategy |
| AI documentation score | 4.4 / 5 | Improved by this AI-first layer and expanded `AI_CONTEXT.md` |
| Autonomous development score | 3.8 / 5 | Safe for scoped changes, but production operations and audit gaps still require human review |
| Repository intelligence score | 4.2 / 5 | High source-traceability, strong test suite, documented invariants |

## Agent Safety By Domain

| Domain | Can an AI safely modify it? | Conditions |
|---|---|---|
| Architecture docs | Yes | Read ADR index and update decision references |
| Local development docs | Yes | Verify commands and avoid dependency drift |
| Storefront presentation UI | Mostly | Run typecheck/build/e2e when behavior changes |
| Storefront checkout/cart | Conditional | Read checkout contract, variant contract, and backend source-of-truth rules |
| Backend checkout | Conditional | Must inspect `CreateQuickOrder`, idempotency, inventory, and tests first |
| Product variants | Conditional | Must preserve `ProductType`, `product_variant_id`, and ADR 0013 rules |
| Inventory | Conditional | Must preserve stock movement ledger and sellable-unit lookup |
| Tenancy | High caution | Must preserve global scope rules and explicit tenant filters |
| Billing/subscriptions | High caution | Must preserve feature gates, invoice/payment auditability, and money rules |
| Security/2FA | High caution | Do not bypass enforcement; fix the correct flow |
| Staging/production operations | Human supervised | Dated evidence is not current live proof |
| CI/dependencies | Human supervised | User often asks for docs-only boundaries; do not drift CI/deps casually |

## Required Agent Workflow

1. Read `AI_CONTEXT.md`.
2. Read `docs/README.md`.
3. Read the domain-specific docs listed in `docs/README.md`.
4. Inspect current code before editing.
5. Check current git status and avoid staging unrelated changes.
6. Make the smallest change that preserves documented invariants.
7. Run risk-appropriate tests from `docs/TESTING_STRATEGY.md`.
8. Update docs when behavior, tests, contracts, or operational state changes.

## Missing Information For Fully Autonomous Work

- Current production/staging secrets and provider state are intentionally not in
  the repository.
- Monitoring provider and contact routing are not finalized.
- Some Arabic-authoritative documents have no full English translation.
- Audit matrix has partial and unknown areas.
- Current external staging health was not verified during this audit.
- User intent may impose hard docs-only boundaries that agents must honor.

## Readiness By Agent Type

| Agent | Readiness | Notes |
|---|---:|---|
| Codex | High | Strong with local repo inspection and tests |
| Claude Code | High | Needs same docs-first workflow |
| Cursor Agents | Medium-high | Needs explicit context files and path ownership |
| OpenHands | Medium | Use bounded tasks, avoid broad commits in dirty worktree |
| Future autonomous agents | Medium-high | Safe if they obey `AI_CONTEXT.md` and critical paths |

## Prohibited Autonomous Actions

- Do not disable 2FA to solve panel access loops.
- Do not add feature flags that bypass security enforcement.
- Do not trust storefront totals or prices.
- Do not remove tenant filters after `withoutGlobalScope('current_tenant')`.
- Do not change migrations, CI, dependencies, or app code during docs-only tasks.
- Do not claim current external staging or production health without a fresh
  smoke proof.
- Do not edit evidence records as if they were runbooks.

## Best Next Improvements

1. Add a docs link checker and stale-summary checker.
2. Generate route/model/table inventories automatically.
3. Add a full English mirror for Arabic-authoritative contracts or a structured
   bilingual summary.
4. Turn `docs/AUDIT_MATRIX.md` gaps into tracked tasks.
5. Record live staging smoke evidence when operators rerun staging checks.
