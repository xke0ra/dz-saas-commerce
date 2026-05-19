# dz-saas-commerce

`dz-saas-commerce` is a monorepo for a multi-tenant Algerian SaaS commerce platform.

Current shape:

- `backend/`: Laravel 13, Filament 5.6 panels, REST storefront API, tenancy, commerce domains, queues, scheduler, health/readiness checks.
- `storefront/`: Next.js 15.5, React 19, TypeScript customer storefront with products, cart, checkout, order tracking, SEO, and variant picker support.
- `docs/`: canonical project analysis, domain contracts, ADRs, security baseline, testing strategy, staging and operations runbooks.
- `deploy/`: staging, reverse proxy, backup, and supervision examples.

Start with:

1. `docs/README.md`
2. `docs/PROJECT_DEEP_ANALYSIS_AND_AI_ROADMAP_AR.md`
3. `docs/DOMAIN_CONTRACTS_AR.md`
4. `docs/TESTING_STRATEGY.md`
5. `docs/PRODUCTION_READINESS.md`

Important status:

- Real external staging is still pending until VPS/provider, domain or hostname, and staging secrets/variables are available.
- Production launch is not proven yet. Staging proof, monitoring, restore drill, and hardening remain required.
- Business logic, checkout, inventory lifecycle, migrations, dependencies, deploy scripts, and CI should not be changed during documentation-only audit rounds.
