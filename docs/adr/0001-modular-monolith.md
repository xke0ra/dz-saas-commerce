# ADR 0001: Modular Monolith Instead Of Microservices

Date: 2026-05-07

Status: Accepted

## Context

The repository is one product with a Laravel backend, Filament panels, and a separate Next.js storefront. Backend domains are organized through Laravel namespaces such as `Actions`, `Models`, `Policies`, `Jobs`, `Support`, and Filament resources.

The current team/process benefits more from simple deployment, local development, strong tests, and consistent tenant isolation than from service-level distribution.

## Decision

Keep the backend as a modular monolith. Introduce clearer domain module boundaries inside Laravel when complexity grows, but do not split into microservices now.

## Consequences

- Cross-domain business rules should stay in Actions/Support classes, not scattered in controllers or Filament resources.
- Shared transactions for checkout, inventory, billing, and tenant operations remain practical.
- Microservices require a new ADR, deployment design, observability design, and data ownership plan.
