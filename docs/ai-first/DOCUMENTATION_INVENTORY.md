# Documentation Inventory

Last updated: 2026-06-02

## Scoring Method

Scores are from 1 to 5.

- Quality: structure, clarity, traceability, and maintenance hygiene.
- Completeness: coverage of the topic the document claims to own.
- AI usefulness: how directly an autonomous agent can use the document to make safe changes.

Discovery snapshot:

- 86 Markdown/text documentation files were present after this AI-first layer was
  added.
- 133 documentation-like sources were discovered when hidden CI/devcontainer
  files, env examples, Docker files, YAML/JSON manifests, shell runbooks, service
  examples, and timer examples were included.
- `*:Zone.Identifier` files are Windows metadata artifacts and are excluded from
  quality scoring.

Scores for configuration and script files measure documentation usefulness, not
runtime implementation quality.

## Inventory

| Path | Purpose | Audience | Quality | Completeness | AI usefulness |
|---|---|---|---:|---:|---:|
| `README.md` | Root project entry point and status summary | Developers, agents | 4 | 3 | 4 |
| `AI_CONTEXT.md` | AI operating manual, updated by this audit | AI agents | 5 | 5 | 5 |
| `CONTRIBUTING.md` | Contribution rules and workflow expectations | Developers, agents | 4 | 4 | 4 |
| `SECURITY.md` | Security reporting policy and scope | Maintainers, reporters | 4 | 3 | 3 |
| `CHANGELOG.md` | Change history | Maintainers | 3 | 3 | 2 |
| `backend/README.md` | Backend stack, domains, commands, health checks | Backend developers, agents | 4 | 4 | 4 |
| `backend/database/seeders/data/README.md` | Algerian geography data provenance | Backend developers | 4 | 4 | 3 |
| `backend/database/seeders/data/LICENSE.kossa-algerian-cities.md` | Source license note for seed data | Maintainers | 3 | 4 | 2 |
| `backend/public/robots.txt` | Static backend crawl policy | Operators | 2 | 2 | 1 |
| `deploy/staging/README.md` | Staging deployment skeleton | Operators, release agents | 4 | 4 | 4 |
| `deploy/staging/GITHUB_ENVIRONMENT.md` | GitHub environment secret/variable contract | Operators | 4 | 4 | 4 |
| `docs/README.md` | Canonical documentation index | All contributors | 5 | 4 | 5 |
| `docs/ARCHITECTURE.md` | System architecture and source-of-truth rules | Architects, agents | 5 | 4 | 5 |
| `docs/DOMAIN_CONTRACTS_SUMMARY.md` | English domain contracts summary | Developers, agents | 5 | 4 | 5 |
| `docs/DOMAIN_CONTRACTS_AR.md` | Authoritative Arabic domain contracts | Developers | 4 | 4 | 4 |
| `docs/TENANCY_RULES.md` | Tenant isolation rules | Backend developers, agents | 5 | 4 | 5 |
| `docs/SECURITY_BASELINE.md` | Security posture, controls, and gaps | Security reviewers, agents | 5 | 4 | 5 |
| `docs/AUDIT_MATRIX.md` | Audit logging coverage by domain | Backend developers | 4 | 3 | 4 |
| `docs/TWO_FACTOR_AUTH_AR.md` | Filament 2FA setup, challenge, reset rules | Operators, developers | 4 | 4 | 4 |
| `docs/STOREFRONT_CART.md` | Cart, checkout, and storefront submission contract | Frontend/backend developers | 5 | 4 | 5 |
| `docs/STOREFRONT_SEO.md` | SEO, crawl, sitemap, robots contract | Frontend developers | 4 | 4 | 4 |
| `docs/STOREFRONT_THEME.md` | Theme settings and storefront sections | Frontend/backend developers | 4 | 4 | 4 |
| `docs/PRODUCTION_READINESS.md` | Production readiness runbook and status | Operators, maintainers | 5 | 4 | 5 |
| `docs/STAGING_DEPLOYMENT_RUNBOOK_AR.md` | Arabic staging deployment runbook | Operators | 5 | 4 | 4 |
| `docs/STAGING_READINESS_CHECKLIST_AR.md` | Staging readiness gate | Operators | 4 | 4 | 4 |
| `docs/BACKUP_RESTORE_RUNBOOK.md` | Backup and restore procedures | Operators | 5 | 4 | 4 |
| `docs/REVERSE_PROXY_RUNBOOK.md` | TLS, Caddy/Nginx, trusted proxy guidance | Operators, backend developers | 4 | 4 | 4 |
| `docs/QUEUE_SCHEDULER_RUNBOOK.md` | Queue worker and scheduler operations | Operators | 4 | 4 | 4 |
| `docs/MONITORING_ALERTING_RUNBOOK.md` | Monitoring and alerting plan | Operators | 4 | 3 | 4 |
| `docs/MONITORING_BASELINE_MATRIX_AR.md` | Alert matrix in Arabic | Operators | 3 | 3 | 3 |
| `docs/ROLLBACK_RUNBOOK.md` | Rollback procedure | Operators | 4 | 3 | 4 |
| `docs/DATABASE_MIGRATION_RUNBOOK.md` | Migration preparation and execution | Backend developers, operators | 5 | 4 | 5 |
| `docs/LOCAL_DEVELOPMENT.md` | Local setup and verification commands | Developers | 4 | 4 | 4 |
| `docs/DEVELOPMENT_WORKFLOW.md` | Inspect-before-change workflow and checklist | Developers, agents | 5 | 4 | 5 |
| `docs/TESTING_STRATEGY.md` | Test requirements and risk-based gates | Developers, agents | 5 | 4 | 5 |
| `docs/CODEX_TASKS_AR.md` | Arabic Codex task rules | Users, agents | 4 | 4 | 4 |
| `docs/guides/ONBOARDING.md` | First-hours onboarding guide | New developers | 4 | 4 | 4 |
| `docs/operations/INCIDENT_RESPONSE.md` | Incident response workflow | Operators | 4 | 3 | 3 |
| `docs/OPERATIONS_NEXT_STEPS_AR.md` | Arabic operations roadmap | Operators, maintainers | 4 | 3 | 3 |
| `docs/PROJECT_DEEP_ANALYSIS_AND_AI_ROADMAP_AR.md` | Deep project analysis and roadmap | Maintainers, agents | 4 | 4 | 4 |
| `docs/ALGERIA_GEOGRAPHY.md` | Algerian geography dataset and 69-wilaya risk | Backend developers | 4 | 4 | 4 |
| `docs/templates/README.md` | Template archive index | Operators | 4 | 4 | 3 |
| `docs/templates/BACKUP_RESTORE_DRILL_EVIDENCE_TEMPLATE.md` | Blank restore-drill evidence form | Operators | 4 | 4 | 3 |
| `docs/templates/STAGING_SMOKE_PROOF_TEMPLATE_AR.md` | Blank staging smoke evidence form | Operators | 4 | 4 | 3 |
| `docs/evidence/README.md` | Evidence archive index | Operators, auditors | 4 | 4 | 3 |
| `docs/evidence/STAGING_SMOKE_PROOF_2026-05-26_AR.md` | Dated external staging smoke proof | Operators, auditors | 4 | 4 | 4 |
| `docs/evidence/STAGING_POSTGRES_BACKUP_AUTOMATION_PROOF_2026-05-28.md` | Dated staging backup automation proof | Operators, auditors | 4 | 4 | 4 |
| `docs/evidence/BACKUP_RESTORE_DRILL_PROOF_2026-05-28.md` | Dated restore drill proof | Operators, auditors | 4 | 4 | 4 |
| `docs/adr/README.md` | ADR index | Architects, agents | 5 | 4 | 5 |
| `docs/adr/0001-modular-monolith.md` | Modular monolith decision | Architects | 4 | 4 | 4 |
| `docs/adr/0002-shared-database-tenancy.md` | Shared database tenancy decision | Architects, backend developers | 4 | 4 | 5 |
| `docs/adr/0003-laravel-filament-backend.md` | Laravel/Filament backend decision | Architects | 4 | 4 | 4 |
| `docs/adr/0004-nextjs-storefront.md` | Separate Next.js storefront decision | Architects, frontend developers | 4 | 4 | 4 |
| `docs/adr/0005-backend-source-of-truth-for-commerce-money.md` | Backend money authority decision | Developers, agents | 4 | 4 | 5 |
| `docs/adr/0006-do-not-trust-client-totals.md` | Client totals rejection decision | Developers, agents | 4 | 4 | 5 |
| `docs/adr/0007-69-wilayas-not-enabled-now.md` | Geography reform deferral | Developers, operators | 4 | 4 | 4 |
| `docs/adr/0008-marketplace-deferred.md` | Marketplace deferral decision | Product, architects | 4 | 4 | 3 |
| `docs/adr/0009-manual-payments-first.md` | Manual payments first decision | Product, backend developers | 4 | 4 | 4 |
| `docs/adr/0010-algerian-shipping-strategy.md` | Shipping strategy decision | Backend developers | 4 | 4 | 4 |
| `docs/adr/0011-storefront-caching-revalidation.md` | Storefront cache/revalidation decision | Frontend developers | 4 | 3 | 4 |
| `docs/adr/0012-production-deployment-topology.md` | Deployment topology decision | Operators, architects | 4 | 3 | 4 |
| `docs/adr/0013-product-variants-inventory-design.md` | Product variants and inventory decision | Backend/frontend developers | 5 | 5 | 5 |
| `docs/adr/0014-error-tracking-provider.md` | Error tracking provider decision | Operators | 3 | 3 | 3 |
| `docs/adr/0015-cors-policy.md` | CORS policy decision | Backend/frontend developers | 4 | 4 | 4 |
| `docs/adr/0016-api-versioning-strategy.md` | API versioning strategy | Backend/frontend developers | 4 | 3 | 4 |

## AI-First Output Inventory

| Path | Purpose | Audience | Quality | Completeness | AI usefulness |
|---|---|---|---:|---:|---:|
| `docs/ai-first/README.md` | Index for the AI-first documentation layer | Agents, maintainers | 5 | 4 | 5 |
| `docs/ai-first/DOCUMENTATION_INVENTORY.md` | Documentation and knowledge-source inventory | Maintainers, agents | 5 | 4 | 5 |
| `docs/ai-first/DOCUMENTATION_AUDIT_REPORT.md` | Deep documentation audit findings | Maintainers, agents | 5 | 4 | 5 |
| `docs/ai-first/DOCUMENTATION_DRIFT_REPORT.md` | Documentation-to-code drift assessment | Maintainers, agents | 5 | 4 | 5 |
| `docs/ai-first/AI_READINESS_REPORT.md` | Autonomous-agent readiness scoring and limits | Maintainers, agents | 5 | 4 | 5 |
| `docs/ai-first/KNOWLEDGE_GRAPH.md` | Entity/domain/action graph with source links | Agents, architects | 5 | 4 | 5 |
| `docs/ai-first/DOMAIN_MAP.md` | Bounded-context map | Developers, agents | 5 | 4 | 5 |
| `docs/ai-first/SYSTEM_INVARIANTS.md` | Must-not-violate system rules | Developers, agents | 5 | 4 | 5 |
| `docs/ai-first/AI_CHANGE_GUIDE.md` | Safe AI change workflow and forbidden shortcuts | Agents | 5 | 5 | 5 |
| `docs/ai-first/FILE_OWNERSHIP_MAP.md` | Review ownership by path | Maintainers, agents | 4 | 4 | 5 |
| `docs/ai-first/FEATURE_LIFECYCLES.md` | Lifecycle summaries for core workflows | Developers, agents | 4 | 4 | 5 |
| `docs/ai-first/TESTING_REQUIREMENTS.md` | Risk-based test selector | Developers, agents | 5 | 4 | 5 |
| `docs/ai-first/ARCHITECTURE_GLOSSARY.md` | Normalized project terminology | Developers, agents | 4 | 4 | 4 |
| `docs/ai-first/DECISION_INDEX.md` | ADR coverage summary | Architects, agents | 4 | 4 | 5 |
| `docs/ai-first/DOMAIN_DEPENDENCY_MAP.md` | Cross-domain dependency matrix | Architects, agents | 4 | 4 | 5 |
| `docs/ai-first/RISK_REGISTRY.md` | Consolidated engineering and documentation risks | Maintainers, agents | 4 | 4 | 5 |
| `docs/ai-first/CRITICAL_PATHS.md` | High-risk source paths by workflow | Developers, agents | 5 | 4 | 5 |
| `docs/ai-first/BUSINESS_RULES.md` | Business rules by domain | Developers, agents | 5 | 4 | 5 |
| `docs/ai-first/SECURITY_BOUNDARIES.md` | Security boundaries and forbidden exposure | Security reviewers, agents | 5 | 4 | 5 |
| `docs/ai-first/TENANCY_BOUNDARIES.md` | Tenant boundary model and scope rules | Backend developers, agents | 5 | 4 | 5 |
| `docs/ai-first/DOCUMENTATION_MASTER_REPORT.md` | Executive documentation maturity report | Maintainers | 5 | 4 | 4 |

## Operational And Configuration Knowledge Inventory

| Path | Purpose | Audience | Quality | Completeness | AI usefulness |
|---|---|---|---:|---:|---:|
| `.devcontainer/Dockerfile` | Development container image contract | Developers | 3 | 3 | 3 |
| `.devcontainer/devcontainer.json` | IDE/devcontainer runtime settings | Developers | 3 | 3 | 3 |
| `.devcontainer/docker-compose.yml` | Devcontainer service wiring | Developers | 3 | 3 | 3 |
| `.devcontainer/post-create.sh` | Devcontainer bootstrap steps | Developers | 3 | 3 | 3 |
| `.devcontainer/post-start.sh` | Devcontainer startup steps | Developers | 3 | 3 | 3 |
| `.dockerignore` | Root Docker context exclusions | Maintainers | 2 | 3 | 2 |
| `.gitignore` | Repository ignore policy | Maintainers | 2 | 3 | 2 |
| `.github/workflows/container-images.yml` | Container image CI contract | Maintainers, agents | 3 | 3 | 4 |
| `.github/workflows/quality.yml` | Quality gate CI contract | Maintainers, agents | 4 | 4 | 4 |
| `.github/workflows/staging-smoke.yml` | Staging smoke CI contract | Operators, agents | 4 | 4 | 4 |
| `docker-compose.yml` | Local service topology | Developers | 3 | 4 | 4 |
| `backend/.dockerignore` | Backend Docker context exclusions | Backend developers | 2 | 3 | 2 |
| `backend/.env.example` | Backend environment variable contract | Developers, operators | 4 | 4 | 4 |
| `backend/.gitignore` | Backend ignore policy | Maintainers | 2 | 3 | 2 |
| `backend/Dockerfile` | Backend container build contract | Operators, agents | 3 | 4 | 4 |
| `backend/composer.json` | Backend PHP dependency and script contract | Backend developers, agents | 4 | 4 | 4 |
| `backend/package.json` | Backend frontend/tooling package contract | Backend developers | 3 | 3 | 3 |
| `backend/package-lock.json` | Backend npm lockfile | Backend developers | 2 | 4 | 2 |
| `backend/vite.config.js` | Backend asset build configuration | Backend developers | 3 | 3 | 3 |
| `deploy/backup/backup.env.example` | Backup environment contract | Operators | 4 | 4 | 4 |
| `deploy/backup/bin/object-storage-sync.sh.example` | Object storage backup example | Operators | 4 | 4 | 4 |
| `deploy/backup/bin/postgres-backup.sh.example` | PostgreSQL backup example | Operators | 4 | 4 | 4 |
| `deploy/backup/bin/staging-postgres-backup.sh.example` | Staging PostgreSQL backup example | Operators | 4 | 4 | 4 |
| `deploy/backup/bin/staging-restore-drill.sh.example` | Staging restore-drill example | Operators | 4 | 4 | 4 |
| `deploy/backup/systemd/dz-saas-commerce-object-storage-backup.service.example` | Object storage backup service example | Operators | 3 | 4 | 3 |
| `deploy/backup/systemd/dz-saas-commerce-object-storage-backup.timer.example` | Object storage backup timer example | Operators | 3 | 4 | 3 |
| `deploy/backup/systemd/dz-saas-commerce-postgres-backup.service.example` | PostgreSQL backup service example | Operators | 3 | 4 | 3 |
| `deploy/backup/systemd/dz-saas-commerce-postgres-backup.timer.example` | PostgreSQL backup timer example | Operators | 3 | 4 | 3 |
| `deploy/backup/systemd/mayfair-staging-postgres-backup.service.example` | Mayfair staging backup service example | Operators | 3 | 4 | 3 |
| `deploy/backup/systemd/mayfair-staging-postgres-backup.timer.example` | Mayfair staging backup timer example | Operators | 3 | 4 | 3 |
| `deploy/reverse-proxy/nginx-edge.conf.example` | Nginx edge reverse-proxy example | Operators | 4 | 4 | 4 |
| `deploy/staging/backend.env.example` | Staging backend env contract | Operators | 4 | 4 | 4 |
| `deploy/staging/docker-compose.staging.ephemeral.yml` | Ephemeral staging smoke topology | Operators, agents | 4 | 4 | 4 |
| `deploy/staging/docker-compose.staging.example.yml` | Persistent staging topology example | Operators | 4 | 4 | 4 |
| `deploy/staging/images.env.example` | Staging image tag/digest contract | Operators | 4 | 4 | 4 |
| `deploy/staging/staging-ephemeral-smoke.sh` | Ephemeral staging smoke runner | Operators, agents | 4 | 4 | 4 |
| `deploy/staging/staging-smoke.sh` | External staging smoke runner | Operators, agents | 4 | 4 | 4 |
| `deploy/staging/storefront.env.example` | Staging storefront env contract | Operators | 4 | 4 | 4 |
| `deploy/supervision/systemd/dz-saas-commerce-queue.service.example` | Queue worker service example | Operators | 3 | 4 | 3 |
| `deploy/supervision/systemd/dz-saas-commerce-scheduler.service.example` | Scheduler service example | Operators | 3 | 4 | 3 |
| `scripts/dev/backend-test.sh` | Backend test helper | Developers, agents | 3 | 4 | 4 |
| `scripts/dev/quality-gate.sh` | Local quality gate helper | Developers, agents | 3 | 4 | 4 |
| `scripts/dev/reset-dev-db.sh` | Local database reset helper | Developers | 3 | 3 | 3 |
| `scripts/dev/storefront-check.sh` | Storefront verification helper | Frontend developers, agents | 3 | 4 | 4 |
| `scripts/release/clean-export-check.sh` | Release/export hygiene helper | Maintainers | 3 | 3 | 3 |
| `scripts/security/container-image-scan.sh` | Container image scan helper | Security reviewers, agents | 3 | 3 | 4 |
| `scripts/security/secret-hygiene.sh` | Secret hygiene helper | Security reviewers, agents | 4 | 4 | 4 |
| `storefront/.dockerignore` | Storefront Docker context exclusions | Frontend developers | 2 | 3 | 2 |
| `storefront/.env.example` | Storefront local environment contract | Frontend developers | 4 | 4 | 4 |
| `storefront/.env.production.example` | Storefront production environment contract | Operators | 4 | 4 | 4 |
| `storefront/.gitignore` | Storefront ignore policy | Maintainers | 2 | 3 | 2 |
| `storefront/Dockerfile` | Storefront container build contract | Operators, agents | 3 | 4 | 4 |
| `storefront/next.config.ts` | Next.js runtime/build configuration | Frontend developers, agents | 3 | 4 | 4 |
| `storefront/package.json` | Storefront dependency and script contract | Frontend developers, agents | 4 | 4 | 4 |
| `storefront/playwright.config.ts` | Storefront e2e test configuration | Frontend developers, agents | 3 | 4 | 4 |
| `storefront/pnpm-lock.yaml` | Storefront dependency lockfile | Frontend developers | 2 | 4 | 2 |
| `storefront/pnpm-workspace.yaml` | Storefront workspace configuration | Frontend developers | 2 | 3 | 2 |
| `storefront/postcss.config.mjs` | CSS build configuration | Frontend developers | 2 | 3 | 2 |
| `storefront/tailwind.config.ts` | Tailwind theme/content configuration | Frontend developers | 3 | 4 | 3 |
| `storefront/tsconfig.json` | TypeScript compiler contract | Frontend developers, agents | 3 | 4 | 4 |

## Inventory Findings

- The repository already has a strong documentation base for architecture,
  tenancy, checkout, variants, security, testing, staging, and operations.
- The main documentation gap was not volume. It was missing a consolidated
  AI-first layer that turns scattered knowledge into change rules, ownership,
  critical paths, risk, and invariants.
- Several documents are Arabic-only or Arabic-authoritative. AI agents that
  cannot reason over those documents should rely on the English summaries but
  must not edit Arabic-authoritative behavior without checking the Arabic file.
- Evidence files are dated proof records. They should not be rewritten as
  runbooks and should not be treated as current live health without a new smoke.
- The `*:Zone.Identifier` files are not useful documentation and should be
  cleaned separately if the maintainer wants repository hygiene work.
