# Testing Requirements

Last updated: 2026-06-02

Use this file as a quick selector. The canonical strategy remains
`docs/TESTING_STRATEGY.md`.

## Docs-Only Changes

Required:

- `git diff --check`
- `git status --short`
- `git diff --name-only`
- docs-only diff guard, for example checking changed files stay under `docs/`
  and allowed root documentation files
- secret grep when docs or env examples include secrets/placeholders

Backend/storefront suites are not required for pure docs-only changes.

## Backend Checkout

Read first:

- `docs/STOREFRONT_CART.md`
- `docs/DOMAIN_CONTRACTS_SUMMARY.md`
- ADR 0005, ADR 0006, ADR 0013

Run:

- backend quick checkout tests
- idempotency tests if duplicate/replay behavior changes
- variant inventory tests if `product_variant_id` behavior changes

## Inventory

Run:

- inventory feature tests
- stock movement ledger tests
- order lifecycle tests if settlement/release changes
- return workflow tests if restock changes

## Tenancy

Run:

- tenant foundation tests
- tenant integrity tests
- tenant permission/policy tests
- affected feature tests for any `withoutGlobalScope` change

## Billing

Run:

- billing foundation tests
- billing lifecycle tests
- vendor billing Filament tests if panel behavior changes

## Security/2FA

Run:

- security headers tests
- trusted proxy tests if proxy behavior changes
- 2FA tests
- 2FA reset command tests
- audit tests if audit paths change

## Storefront

Run:

- TypeScript typecheck for type/API changes
- Next build for routing/metadata changes
- Playwright e2e for customer workflow, cart, checkout, SEO, or track-order changes

## Operations

Run only when appropriate:

- shell syntax checks for scripts
- local or ephemeral smoke scripts for deployment changes
- external staging smoke only when infrastructure access and user scope allow it

## Reporting

Report exact commands and outcomes. If a test was not run, say why.
