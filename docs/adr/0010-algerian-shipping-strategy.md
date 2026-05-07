# ADR 0010: Algerian Shipping Strategy

Date: 2026-05-07

Status: Accepted

## Context

The repository includes wilayas, communes, shipping companies, rates, delivery types, shipments, failed delivery reasons, and returns foundations. Real carrier integrations, COD reconciliation, and failed delivery analytics are not implemented yet.

## Decision

Start with internal Algerian shipping configuration and manual/operational shipment workflows. Add provider integrations only after rates, zones, delivery types, COD handling, and reconciliation rules are explicit.

## Consequences

- Shipping rates remain backend-controlled.
- COD collection and reconciliation require a dedicated design before production use.
- Delivery provider integrations need callback security, idempotency, and audit logs.
- Shipping analytics should focus on failed delivery reasons, returns, and reconciliation once real data exists.
