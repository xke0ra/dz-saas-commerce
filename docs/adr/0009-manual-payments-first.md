# ADR 0009: Manual Payments First, Integrations Later

Date: 2026-05-07

Status: Accepted

## Context

The current billing roadmap and code support plans, subscriptions, invoices, subscription payments, manual confirmation, grace/suspension concepts, and operational lifecycle. Payment gateway integrations and webhooks are not production-ready yet.

## Decision

Use manual payment confirmation as the first operational billing strategy, then add payment integrations later with webhook security and reconciliation.

## Consequences

- Manual confirmations must be auditable.
- Future payment proofs require upload validation and private storage.
- Webhook/callback security is required before automated payment mutation.
- Dunning, reminders, ledger, and revenue dashboards can evolve after the manual flow is reliable.
