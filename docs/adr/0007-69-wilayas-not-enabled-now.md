# ADR 0007: 69 Wilayas Are Not Enabled Now

Date: 2026-05-07

Status: Accepted

## Context

The repository includes Algerian geography seeding for the current operational checkout dataset. The roadmap analysis classifies the 69-wilaya change as research/future migration risk unless a reliable official dataset and migration plan exist.

## Decision

Do not enable a 69-wilaya production feature now.

## Consequences

- Keep current geography data stable for checkout and shipping until a verified source and migration plan are available.
- Treat 69 wilayas as research/future migration work, not an immediate feature.
- Any future migration must handle existing orders, shipping rates, communes, analytics, and merchant configuration.
