# Domain Map

Last updated: 2026-06-02

## Bounded Contexts

| Context | Current Status | Primary Paths | Responsibility |
|---|---|---|---|
| Platform identity | Implemented | `User`, panel providers, 2FA support | Panel access, platform roles, 2FA |
| Tenant management | Implemented | tenancy models/actions/support | Tenant membership, roles, invitations |
| Store management | Implemented | store models, Filament resources, readiness checker | Store status, settings, theme, exposure readiness |
| Catalog | Implemented | product/category resources, search action | Products, categories, images, visibility |
| Variant catalog | Implemented | variants schema, validator, resources | Options, option values, variants, pricing fallback |
| Inventory | Implemented | inventory actions, stock movements | Reservation, settlement, release, restock, manual adjustment |
| Checkout | Implemented | `CreateQuickOrder`, storefront API | Order creation, totals, idempotency, reservation |
| Order operations | Implemented | order actions, policies, histories | Status transitions, vendor workflow |
| Payment operations | Implemented manual-first | payment actions, payment methods | COD/manual payments, refunds, failures |
| Shipping | Implemented base | shipping actions/resources | Rates, shipments, delivery transitions |
| Returns | Implemented base | return actions/resources | Review, receive, refund, restock |
| SaaS billing | Implemented base | billing actions, job, notifications | Plans, subscriptions, invoices, payment review |
| Couponing | Implemented base | coupon model/action | Discount calculation and redemption |
| Domain management | Implemented base | domain model/action/job | Custom domain verification and status |
| Storefront | Implemented base | `storefront/src` | Public pages, cart, checkout UI, SEO |
| Operations | Partially implemented | `deploy/`, runbooks, health checker | Deploy, smoke, backup, monitoring, rollback |
| Analytics | Implemented base | analytics support/widgets | Tenant order analytics |
| Support | Implemented base | support ticket models/resources | Platform and vendor support tickets |

## Domain Ownership

| Domain | Owner Type | Review Required |
|---|---|---|
| Tenancy | Backend/security owner | Always |
| Checkout and inventory | Backend commerce owner | Always |
| Billing | Backend/platform owner | Always |
| Security/2FA/audit | Security owner | Always |
| Storefront UX | Frontend owner | When changing checkout, API, SEO, or theme contracts |
| Operations | DevOps/operator | When changing deploy, smoke, backup, monitoring, rollback |
| Documentation | Maintainer or domain owner | When behavior or evidence changes |

## Cross-Domain Boundaries

- Checkout crosses catalog, variants, inventory, shipping, payments, coupons,
  tenant, billing feature gates, and storefront API.
- Tenant resolution crosses host/domain/store/user/session state.
- Store readiness crosses tenancy, store settings, theme settings, payment
  methods, shipping rates, product visibility, variants, and inventory.
- Billing crosses plan limits, subscriptions, invoices, payments, feature gates,
  and notifications.
- Operations crosses container images, environment variables, reverse proxy,
  health checks, queues, scheduler, backups, and evidence docs.

## Domain Change Rule

If a change crosses two or more contexts, it needs:

- pre-edit inspection of source files
- domain docs update
- targeted tests
- risk note in PR summary or task summary
- no unrelated refactor
