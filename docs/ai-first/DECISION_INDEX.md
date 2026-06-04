# Decision Index

Last updated: 2026-06-02

This index summarizes current ADR coverage. The canonical ADR files remain under
`docs/adr/`.

| ADR | Decision | Status For Agents |
|---|---|---|
| 0001 | Modular monolith instead of microservices | Preserve unless a new ADR supersedes it |
| 0002 | Shared database tenancy | Preserve strict tenant isolation |
| 0003 | Laravel and Filament backend | Preserve dashboard architecture |
| 0004 | Separate Next.js storefront | Preserve backend/storefront split |
| 0005 | Backend is source of truth for commerce money | Never move trusted totals to client |
| 0006 | Do not trust client totals | Reject client financial authority |
| 0007 | 69 wilayas are not enabled now | Do not activate 69-wilaya checkout without migration plan |
| 0008 | Marketplace is deferred | Do not build marketplace assumptions into core flows |
| 0009 | Manual payments first | Do not assume payment gateway exists |
| 0010 | Algerian shipping strategy | Preserve wilaya/commune delivery model |
| 0011 | Storefront caching and revalidation | Use no-store/dynamic behavior unless cache design is updated |
| 0012 | Production deployment topology | Use documented staging/production topology as current direction |
| 0013 | Product variants and inventory design | Preserve sellable-unit and variant inventory semantics |
| 0014 | Error tracking provider | Provider selection deferred or pending implementation |
| 0015 | CORS policy | Keep public API CORS constrained and intentional |
| 0016 | API versioning strategy | Versioning strategy exists, but current routes are not versioned |

## Decision Gaps

Potential future ADRs:

- Payment gateway integration.
- Marketplace activation.
- Large-catalog sitemap indexing.
- Bulk product/import export.
- Production monitoring provider implementation, if ADR 0014 remains abstract.
- Cloudflare/custom-domain automation.
- Data retention and privacy policy.

## Agent Rule

If a change contradicts an ADR, do not proceed silently. Either keep the change
inside the ADR boundary or propose a new ADR.
