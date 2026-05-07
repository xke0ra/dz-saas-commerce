# ADR 0012: Production Deployment Topology

Date: 2026-05-07

Status: Proposed

## Context

Production foundation now includes Dockerfiles, env production examples, health/readiness checks, reverse proxy examples, queue/scheduler supervision examples, backup automation examples, and monitoring runbooks. Staging deployment is not yet proven.

## Decision

Use a topology with reverse proxy/TLS at the edge, separate backend web, queue worker, scheduler, and storefront processes, managed PostgreSQL, Redis, S3-compatible storage, and Meilisearch.

## Consequences

- Queue worker and scheduler must run as separate supervised processes.
- Readiness checks must gate traffic.
- Migrations must be operator-controlled, not automatic entrypoint side effects.
- The ADR becomes `Accepted` only after staging deployment validates proxy, process supervision, backups, monitoring, and rollback procedures.
