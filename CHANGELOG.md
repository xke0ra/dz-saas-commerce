# Changelog

All notable changes to `dz-saas-commerce` are recorded here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Entries are grouped by staging deploy or significant milestone, not by semantic version.

---

## [Unreleased]

### In Progress
- Error tracking provider selection (ADR 0014 — open)
- CORS policy documentation (ADR 0015 — open)
- Storefront caching/revalidation strategy (ADR 0011 — Proposed)
- Production deployment topology proof (ADR 0012 — Proposed)

---

## [staging-20260528] — 2026-05-28

### Added
- Staging PostgreSQL backup automation installed and scheduled via systemd timer (`mayfair-staging-postgres-backup.timer`)
- Restore drill executed on isolated temporary database — PASS (see `docs/evidence/BACKUP_RESTORE_DRILL_PROOF_2026-05-28.md`)
- `docs/DOMAIN_CONTRACTS_SUMMARY.md` — English summary of Arabic domain contracts
- ADR 0014, ADR 0015, ADR 0016 — error tracking, CORS, API versioning decisions opened
- `docs/guides/ONBOARDING.md` — new developer guide
- `docs/operations/INCIDENT_RESPONSE.md` — incident escalation playbook
- `docs/evidence/` subfolder for point-in-time proof documents
- `docs/templates/` subfolder for fill-in templates
- `SECURITY.md` — vulnerability disclosure policy

### Changed
- `docs/BACKUP_RESTORE_RUNBOOK.md` — updated "Current Status": staging restore drill is now COMPLETE
- `docs/adr/0011` and `docs/adr/0012` — added acceptance criteria
- `docs/adr/README.md` — updated index with ADRs 0014-0016
- `docs/README.md` — reflects new `evidence/` and `templates/` structure
- `backend/README.md` — rewritten from generic Laravel boilerplate to project-specific content

### Removed
- Root-level AI-generated audit reports (contained factual errors):
  - `AUDIT_GUIDE.md`
  - `REPORTS_INDEX.md`
  - `EXECUTIVE_SUMMARY.md`
  - `COMPREHENSIVE_TECHNICAL_AUDIT.md`
  - `COMPREHENSIVE_TECHNICAL_AUDIT_PART2.md`
  - `IMPLEMENTATION_ROADMAP.md`
- `save.sh` — personal developer script not appropriate for version control

---

## [staging-20260526] — 2026-05-26

### Added
- Mandatory Filament 2FA setup/challenge smoke proof on `mayfairs.app` (commit `045c264`)
- Staging deployment on DigitalOcean `mayfair-vps` (Frankfurt FRA1, Ubuntu 24.04)
- Caddy public TLS → internal Nginx edge (`127.0.0.1:8080`) topology proven
- Demo tenant/store operational on `https://mayfairs.app` with COD, shipping rates, products, inventory
- `docs/evidence/STAGING_SMOKE_PROOF_2026-05-26_AR.md` — staging evidence
- `docs/adr/0013` — product variants and sellable-unit inventory design (Accepted)
- `docs/TWO_FACTOR_AUTH_AR.md` — 2FA policy documentation
- `docs/STAGING_DEPLOYMENT_RUNBOOK_AR.md`, `docs/STAGING_READINESS_CHECKLIST_AR.md`
- `docs/MONITORING_BASELINE_MATRIX_AR.md`

### Changed
- `EnsurePanelTwoFactor` middleware: fixed redirect loop on 2FA setup flow
- `docs/OPERATIONS_NEXT_STEPS_AR.md` updated with Phase A (backup) and Phase B (monitoring) plans
- `docs/PRODUCTION_READINESS.md` updated with staging proof

---

## [ci-baseline-20260512] — 2026-05-12

### Added
- Product variant schema: `product_variants`, `product_options`, `product_option_values`, `product_variant_option_values` tables
- `activate_variant_inventory_uniqueness` migration — sellable-unit uniqueness at DB level
- `add_product_type_to_products_table` migration
- Storefront variant picker UI (`product-variant-purchase-panel.tsx`)
- Cart sellable unit key: `product_id + product_variant_id`
- `ProductType` enum enforced in checkout backend
- `STOREFRONT_CART.md` updated for variant checkout contract
- Playwright e2e baseline: 6 passed on storefront smoke
- GitHub Container Registry image publish workflow
- `docs/LOCAL_DEVELOPMENT.md`, `docs/STOREFRONT_SEO.md`, `docs/STOREFRONT_THEME.md`

---

## [checkout-hardening-20260507] — 2026-05-07

### Added
- Checkout idempotency table: `checkout_idempotency_records`
- `CheckoutIdempotency` support class — deduplication with `Idempotency-Key` header
- `CheckoutAbuseGuard` — IP + phone + store rate limiting (3-layer)
- `stock_movements` table — append-only inventory ledger
- `SettleOrderInventory`, `ReleaseOrderInventoryReservations`, `RestockOrderReturn` actions
- `ProcessBillingLifecycle` — grace periods, renewal invoices, suspension
- `add_two_factor_authentication_to_users_table` migration
- `BACKUP_RESTORE_RUNBOOK.md`, `MONITORING_ALERTING_RUNBOOK.md`, `QUEUE_SCHEDULER_RUNBOOK.md`, `REVERSE_PROXY_RUNBOOK.md`
- ADRs 0001-0012 (initial set)
- `docs/SECURITY_BASELINE.md`, `docs/TENANCY_RULES.md`, `docs/AUDIT_MATRIX.md`
- `add_tenant_integrity_constraints` migration — composite FK constraints at PostgreSQL level
- `PRODUCTION_READINESS.md` initial version
