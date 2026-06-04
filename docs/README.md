# Documentation Index

Last updated: 2026-05-31

This is the daily starting point for any developer or Codex session working on `dz-saas-commerce`. Read the documents for the domain you are about to touch. Do not rely on memory alone when modifying sensitive logic.

> **Language note:** Documents marked `(AR)` are Arabic-only. Key Arabic documents have English summaries — see the list below.

---

## Where To Start

1. Read `ARCHITECTURE.md` to understand the system shape and the change rule.
2. Read `adr/README.md` and any ADR relevant to the area you're changing.
3. Read the domain-specific document listed under "Read Before Editing" below.
4. Read `TESTING_STRATEGY.md` before running or modifying tests.

New to the project? Start with `docs/guides/ONBOARDING.md`.

---

## Strategy And Architecture

- `ARCHITECTURE.md` — monorepo shape, modular monolith, domains, stack, change rule. **Read this first.**
- `ai-first/README.md` — AI-first audit layer: inventory, drift, invariants, ownership, risks, critical paths, and agent workflow.
- `DOMAIN_CONTRACTS_SUMMARY.md` — English summary of all domain behavioral contracts (checkout, inventory, billing, tenancy, security, store readiness).
- `DOMAIN_CONTRACTS_AR.md` — Full Arabic version of domain contracts (authoritative; read for Arabic speakers or before any contract modification).
- `adr/README.md` — Index of all 16 architecture decisions.

---

## Security And Tenancy

- `TENANCY_RULES.md` — Tenant isolation rules and `withoutGlobalScope` operating contract. **Read before any query change.**
- `SECURITY_BASELINE.md` — Security posture, controls, known gaps, timeline.
- `AUDIT_MATRIX.md` — Audit event coverage by domain; `implemented`, `partial`, `unknown` status.
- `TWO_FACTOR_AUTH_AR.md` (AR) — 2FA policy for admin/vendor/support panels, recovery codes, enforcement limits.

---

## Storefront

- `STOREFRONT_CART.md` — Cart and checkout contract: what storefront sends, what backend owns.
- `STOREFRONT_SEO.md` — SEO and crawl contract: sitemap, robots, structured data, canonical URLs.
- `STOREFRONT_THEME.md` — Home sections, theme elements, trust badges, mobile UX.

---

## Operations And Deployment

- `PRODUCTION_READINESS.md` — Production prerequisites, current status, what is proven vs. pending.
- `operations/INCIDENT_RESPONSE.md` — Incident classification, response steps, escalation, common scenarios.
- `OPERATIONS_NEXT_STEPS_AR.md` (AR) — Next phase operations plan linking backup, monitoring, and rollback phases.
- `STAGING_READINESS_CHECKLIST_AR.md` (AR) — Pre-smoke checklist before any staging deployment.
- `STAGING_DEPLOYMENT_RUNBOOK_AR.md` (AR) — Step-by-step staging deployment on DigitalOcean/Caddy/Nginx.

---

## Runbooks

- `BACKUP_RESTORE_RUNBOOK.md` — PostgreSQL and object storage backup, restore drill procedure.
- `MONITORING_ALERTING_RUNBOOK.md` — Monitoring strategy, alert routing, log channels.
- `MONITORING_BASELINE_MATRIX_AR.md` (AR) — Alert thresholds per component: source, severity, action.
- `QUEUE_SCHEDULER_RUNBOOK.md` — Queue worker and scheduler supervision, restart procedures.
- `REVERSE_PROXY_RUNBOOK.md` — TLS, Caddy/Nginx topology, trusted proxy configuration.

---

## Testing And CI

- `TESTING_STRATEGY.md` — Test philosophy, required coverage, verification commands, CI gate rules.
- `.github/workflows/quality.yml` — The actual CI source (5 quality gates).
- `LOCAL_DEVELOPMENT.md` — Local setup, Docker Compose services, credentials, verification commands.

---

## Developer Guides

- `guides/ONBOARDING.md` — First 2 hours for a new developer: what to read, how to set up, key concepts.
- `DEVELOPMENT_WORKFLOW.md` — Working process, inspect-before-change rule, commit-ready checklist.
- `CODEX_TASKS_AR.md` (AR) — Rules for giving tasks to AI agents, task template, Definition of Done.

---

## Algeria-Specific

- `ALGERIA_GEOGRAPHY.md` — 58 wilayas / 1,541 communes dataset, source, 69-wilaya territorial reform risk.

---

## ADRs

- `adr/README.md` — Index of all 16 ADRs with status and acceptance criteria.
- Read the relevant ADR before changing architecture, tenancy, money, caching, deployment, marketplace, or shipping.

---

## Evidence Archive (`docs/evidence/`)

Point-in-time proof records for operational events. Not runbooks — records of what was done.

- `evidence/BACKUP_RESTORE_DRILL_PROOF_2026-05-28.md` — Staging restore drill proof (PASS)
- `evidence/STAGING_POSTGRES_BACKUP_AUTOMATION_PROOF_2026-05-28.md` — Backup automation installation proof (PASS)
- `evidence/STAGING_SMOKE_PROOF_2026-05-26_AR.md` (AR) — External staging smoke after 2FA fix (PASS)

---

## Templates (`docs/templates/`)

Blank forms to fill in after operational events. Copy to `docs/evidence/` before filling.

- `templates/BACKUP_RESTORE_DRILL_EVIDENCE_TEMPLATE.md`
- `templates/STAGING_SMOKE_PROOF_TEMPLATE_AR.md` (AR)

---

## Read Before Editing

| Changing... | Read these first |
|-------------|-----------------|
| Checkout / order creation | `STOREFRONT_CART.md`, `DOMAIN_CONTRACTS_SUMMARY.md §2`, `adr/0005-*.md`, `adr/0006-*.md` |
| Billing / subscriptions | `DOMAIN_CONTRACTS_SUMMARY.md §6`, `SECURITY_BASELINE.md`, `TESTING_STRATEGY.md` |
| Inventory / stock movements | `DOMAIN_CONTRACTS_SUMMARY.md §4-5`, `AUDIT_MATRIX.md`, `TENANCY_RULES.md` |
| Product variants | `DOMAIN_CONTRACTS_SUMMARY.md §3`, `STOREFRONT_CART.md`, `adr/0013-*.md` |
| Tenancy / `withoutGlobalScope` | `TENANCY_RULES.md`, `SECURITY_BASELINE.md`, `adr/0002-*.md` |
| Storefront | `STOREFRONT_CART.md`, `STOREFRONT_SEO.md`, `STOREFRONT_THEME.md`, `LOCAL_DEVELOPMENT.md` |
| Deployment / infrastructure | `PRODUCTION_READINESS.md`, `OPERATIONS_NEXT_STEPS_AR.md`, `REVERSE_PROXY_RUNBOOK.md` |
| Security / audit | `SECURITY_BASELINE.md`, `AUDIT_MATRIX.md`, `TENANCY_RULES.md` |
| Architecture | `ARCHITECTURE.md`, `adr/` (all relevant) |
| AI agent work / broad audit | `AI_CONTEXT.md`, `ai-first/README.md`, `ai-first/AI_CHANGE_GUIDE.md`, relevant domain docs |

---

## Documentation Rule

Any feature, architectural change, or modification to sensitive behavior **must update the relevant documents in the same change**, or explicitly state why no update is needed.

Arabic-only documents (`*_AR.md`) are authoritative for their content. If modifying a domain covered by an Arabic-only document, update that document or note the change in its corresponding English summary.
