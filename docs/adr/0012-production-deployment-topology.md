# ADR 0012: Production Deployment Topology

Date: 2026-05-27  
Last updated: 2026-05-31

Status: Proposed

## Context

Production foundation now includes Dockerfiles, env production examples, health/readiness checks, reverse proxy examples, queue/scheduler supervision examples, backup automation examples, and monitoring runbooks.

Staging partially proven on DigitalOcean `mayfair-vps` (Frankfurt FRA1) as of 2026-05-26:

- ✅ Caddy public TLS → internal Nginx edge (`127.0.0.1:8080`)
- ✅ HTTPS Filament/Livewire asset generation (no mixed content)
- ✅ Mandatory 2FA setup/challenge on admin panel
- ✅ Demo storefront at `https://mayfairs.app`
- ✅ Staging PostgreSQL backup automation installed and scheduled (2026-05-28)
- ✅ Restore drill executed on isolated temporary database (2026-05-28)

Not yet proven:

- ❌ Production monitoring/alerting integration
- ❌ Centralized log aggregation
- ❌ Rollback procedure proof (deploy-then-rollback drill)
- ❌ Cloudflare Proxied mode (currently DNS only)
- ❌ Production-grade managed PostgreSQL (staging uses Docker Compose PG)
- ❌ Production automated backup with offsite storage

## Decision

Use a topology with:

- Reverse proxy / TLS termination at the edge (Caddy or CDN + Nginx)
- Separate backend web, queue worker, scheduler, and storefront processes
- Managed PostgreSQL (DigitalOcean Managed PG or equivalent)
- Managed Redis
- S3-compatible object storage
- Meilisearch (self-managed or Meilisearch Cloud)

Processes run as separate supervised containers (Docker Compose or equivalent). Migrations are operator-controlled, not automatic entrypoint side effects.

## Consequences

- Queue worker and scheduler must run as separate supervised processes (not inside PHP-FPM).
- Readiness checks must gate traffic on every deploy.
- Migrations must be run manually before new instances start taking traffic.
- `EDGE_PORT` must be bound to a private address (`127.0.0.1:PORT`) when Caddy or a load balancer sits in front.
- `TRUSTED_PROXIES` must be set to the actual proxy IP range, not `*`, in production.

## Acceptance Criteria

This ADR moves from `Proposed` to `Accepted` when all of the following are proven in the production environment (not staging):

1. **Backup automation deployed** — automated daily backups running and monitored; backup failure triggers an alert.
2. **Monitoring and alerting operational** — liveness, readiness, 5xx rate, failed jobs, and TLS expiry are all monitored with alert routing to a human channel.
3. **Rollback drill executed** — at least one deploy-then-rollback cycle completed and documented.
4. **Centralized log collection** — logs reachable from a centralized system (not just `docker logs`).
5. **Production topology documented** — actual production IP, provider, topology, and any deviations from the staging topology recorded in this ADR.

Until then, this ADR remains `Proposed` and staging is not production.
