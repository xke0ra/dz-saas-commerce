# dz-saas-commerce

`dz-saas-commerce` is a monorepo for a multi-tenant Algerian SaaS commerce platform.

Current shape:

- `backend/`: Laravel 13, Filament 5.6 panels, REST storefront API, tenancy, commerce domains, queues, scheduler, health/readiness checks.
- `storefront/`: Next.js 15.5, React 19, TypeScript customer storefront with products, cart, checkout, order tracking, SEO, and variant picker support.
- `docs/`: canonical project analysis, domain contracts, ADRs, security baseline, testing strategy, staging and operations runbooks.
- `deploy/`: staging, reverse proxy, backup, and supervision examples.

Start with:

1. `docs/README.md`
2. `docs/ARCHITECTURE.md`
3. `docs/DOMAIN_CONTRACTS_SUMMARY.md`
4. `docs/TESTING_STRATEGY.md`
5. `docs/PRODUCTION_READINESS.md`

Important status:

- External staging is proven on DigitalOcean `mayfair-vps` (FRA1) at `mayfairs.app` as of 2026-05-26. See `docs/evidence/STAGING_SMOKE_PROOF_2026-05-26_AR.md`.
- Staging PostgreSQL backup automation deployed and restore drill executed 2026-05-28. See `docs/evidence/`.
- Production launch is not proven yet. Monitoring/alerting integration, error tracking, rollback proof, and production hardening remain required.
- Business logic, checkout, inventory lifecycle, migrations, dependencies, deploy scripts, and CI should not be changed during documentation-only audit rounds.
