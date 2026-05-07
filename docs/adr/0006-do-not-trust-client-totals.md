# ADR 0006: Do Not Trust Client Totals

Date: 2026-05-07

Status: Accepted

## Context

The storefront can display cart and checkout totals, but user-controlled clients can replay, edit, or forge payloads. The backend already recalculates checkout totals and protects duplicate submissions through idempotency.

## Decision

Never trust client-submitted totals, discounts, shipping fees, payment amounts, or inventory availability.

## Consequences

- Checkout requests should submit identifiers, quantities, and customer inputs, not authoritative monetary totals.
- Tests must assert server-side total calculation when checkout behavior changes.
- Any future mobile app or public API follows the same rule.
- This ADR reinforces ADR 0005 and must not be weakened without a security review.
