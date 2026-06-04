# AI Change Guide

Last updated: 2026-06-02

## Prime Directive

Make small, source-faithful changes. Do not infer product rules from UI text or
memory. Inspect the current files and preserve documented invariants.

## Required Workflow

1. Check `git status --short --branch`.
2. Identify existing user changes. Do not revert or stage unrelated changes.
3. Read `AI_CONTEXT.md`.
4. Read `docs/README.md`.
5. Read the relevant domain docs and ADRs.
6. Inspect source files before editing.
7. State the intended integration point if the change is high risk.
8. Edit only the files needed.
9. Run targeted verification from `TESTING_REQUIREMENTS.md`.
10. Report exact verification results.

## High-Risk Pre-Edit Scan

Required before editing:

- checkout or cart behavior
- inventory quantities, reservations, or stock movements
- tenancy scopes or tenant resolution
- billing lifecycle or feature gates
- 2FA, panel access, security headers, audit logs
- migrations
- deployment, staging, CI, or dependencies

The scan should answer:

- What flow currently owns the behavior?
- What files are source of truth?
- What docs already describe the behavior?
- What tests currently protect it?
- What is the narrow integration point?

## Docs-Only Mode

When the user requests docs-only work:

- Do not change application code.
- Do not change CI.
- Do not change dependencies.
- Do not add migrations.
- Do not run broad test suites unless needed for verification.
- Use `git diff --name-only` and a docs-only guard before completion.

## Forbidden Shortcuts

- Do not disable 2FA as a fix.
- Do not add a bypass flag for security enforcement.
- Do not move business logic into controllers, Filament resource callbacks, or
  Next.js components.
- Do not trust client totals.
- Do not bypass tenant isolation.
- Do not treat dated evidence as current external proof.
- Do not commit or stage unrelated dirty worktree changes.

## Safe Edit Patterns

| Change Type | Preferred Pattern |
|---|---|
| Backend domain rule | Action/support class plus focused tests and docs |
| Filament UI action | Thin resource callback calling action/support layer |
| Storefront behavior | typed frontend change plus API contract review |
| Checkout validation | request/action validation plus backend feature tests |
| Operation runbook | docs/templates/evidence separation |
| Security command | explicit target, reason, dry-run semantics, tests |
| Documentation audit | add/update docs, verify docs-only diff |

## Completion Standard

A task is complete only when:

- the requested change exists
- verification was attempted and reported
- docs are updated if behavior changed
- no unrelated files were modified by the agent
- limitations are stated plainly
