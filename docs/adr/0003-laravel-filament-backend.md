# ADR 0003: Laravel And Filament For The Backend

Date: 2026-05-07

Status: Accepted

## Context

The backend is Laravel 13 with Filament 5.6 panels for platform admin, vendor workflows, and support workflows. The repository already contains Laravel policies, tests, queues, scheduler commands, and domain Actions.

## Decision

Continue using Laravel as the backend framework and Filament for admin/vendor/support operational panels.

## Consequences

- Filament resources should remain orchestration/UI layers.
- Business logic belongs in Actions/Support classes where possible.
- Authorization must be implemented through policies and tenant permissions.
- Replacing Filament or Laravel requires a superseding ADR and migration plan.
