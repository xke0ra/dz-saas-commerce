# ADR 0005: Backend Is The Source Of Truth For Commerce Money

Date: 2026-05-07

Status: Accepted

## Context

Checkout creates orders through Laravel. The backend calculates prices, discounts, shipping fees, inventory effects, payments, and totals. Checkout idempotency and abuse protection also live in Laravel.

## Decision

The Laravel backend is the only source of truth for prices, discounts, shipping fees, inventory reservations, order totals, payments, subscriptions, and invoices.

## Consequences

- Frontend totals are display hints only.
- New money fields require database constraints, backend validation, and tests.
- Payment/shipping integrations must verify callback authenticity and re-read backend state before mutation.
- Filament actions that affect money must use domain Actions and audit logs.
