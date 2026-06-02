# AI-First Documentation Index

Last updated: 2026-06-02

This directory contains the documentation intelligence layer for `dz-saas-commerce`.
It is intended for human maintainers and autonomous coding agents that need a
source-faithful map before changing the repository.

## Core Reports

- `DOCUMENTATION_INVENTORY.md` - inventory of documentation sources with purpose, audience, and usefulness scores.
- `DOCUMENTATION_AUDIT_REPORT.md` - accuracy, completeness, consistency, and maintainability findings.
- `DOCUMENTATION_DRIFT_REPORT.md` - documentation-to-code drift analysis.
- `AI_READINESS_REPORT.md` - readiness of domains for Codex, Claude Code, Cursor agents, OpenHands, and future AI contributors.
- `DOCUMENTATION_MASTER_REPORT.md` - executive summary, maturity scores, roadmap, and top improvements.

## Project Knowledge

- `KNOWLEDGE_GRAPH.md` - domain/entity/service/policy/workflow knowledge graph.
- `DOMAIN_MAP.md` - bounded contexts and operational responsibilities.
- `SYSTEM_INVARIANTS.md` - rules that must never be violated.
- `BUSINESS_RULES.md` - source-of-truth business rules by domain.
- `ARCHITECTURE_GLOSSARY.md` - normalized terminology.
- `DECISION_INDEX.md` - ADR and decision coverage.

## Change Guidance

- `AI_CHANGE_GUIDE.md` - safe workflow for AI agents and maintainers.
- `FILE_OWNERSHIP_MAP.md` - ownership and review map by path.
- `FEATURE_LIFECYCLES.md` - lifecycle maps for critical features.
- `TESTING_REQUIREMENTS.md` - required verification by change type.
- `DOMAIN_DEPENDENCY_MAP.md` - dependencies between domains.
- `RISK_REGISTRY.md` - risk register and mitigations.
- `CRITICAL_PATHS.md` - flows where regressions have high blast radius.
- `SECURITY_BOUNDARIES.md` - security boundaries and forbidden shortcuts.
- `TENANCY_BOUNDARIES.md` - tenant isolation boundaries.

## Evidence Boundary

These reports use repository files as evidence. Staging proof documents under
`docs/evidence/` are treated as dated evidence records. They do not prove that
external infrastructure is currently healthy unless a live smoke run is executed
and recorded separately.
