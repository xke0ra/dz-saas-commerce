# Stage 11: CI/CD and Deployment Audit

**Audit Date:** 2026-10-01
**Audit Branch:** `main`
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`

## 1. Workflows (actual filenames verified by listing)

`.github/workflows/quality.yml`, `container-images.yml`, `staging-smoke.yml`. (Note: `docs/audit/stage-00-baseline.md:44-46` cites different filenames — audit-internal drift, corrected here.)

- `quality.yml` (225 lines, triggers PR + push main/master, no top-level `permissions` → default GITHUB_TOKEN scope): jobs repository-hygiene (secret-hygiene.sh + clean-export-check.sh) → backend (postgres:17 service + pg_isready, PHP 8.3, `composer validate --strict`, install, `composer audit`, `pint --test`, migrate:fresh --seed, `system:health --scope=ready`, `artisan test`, `route:list`) → storefront (Node 24, pnpm 11.1.2, frozen lockfile, `pnpm audit`, typecheck, build) → docker-check (Trivy 0.70.0, buildx check + build + `container-image-scan.sh`) → e2e (chromium, `test:e2e`, artifacts on failure).
- `container-images.yml` (139 lines, `workflow_dispatch` channel staging|production + `push tags v*.*.*`; `permissions contents:read packages:write`): matrix backend/storefront, `environment` = channel, production guard requires tag dispatch, GHCR login, tags `sha-<12>` + requested + channel, Trivy scan before push, manual push loop. Proven tags `staging-20260512-096bc05` + digest recorded (`PRODUCTION_READINESS.md:142-146`, dated).
- `staging-smoke.yml` (297 lines, `workflow_dispatch` only; `permissions contents:read packages:read`; concurrency `staging-smoke`; `environment: staging`): 7 `STAGING_*` secrets + 15 `STAGING_*` vars contract check, renders ignored env files, delegates to `staging-smoke.sh` / `staging-ephemeral-smoke.sh` (modes validate|pull|up|verify|all|down). No auto-deploy on push; no prod deploy job.

## 2. Deployment topology (docs + deploy/)

Edge `nginx:1.27-alpine` plain HTTP on `${EDGE_PORT:-8080}` (default bound `127.0.0.1:8080` staging) behind external Caddy TLS (`docs/REVERSE_PROXY_RUNBOOK.md:38-49`); backend-queue (`queue:work redis --tries=3 --timeout=90 --sleep=3 --max-time=3600`) + backend-scheduler (`schedule:work`) as compose services; immutable-image guard `${BACKEND_IMAGE:?}`; TLS termination outside repo; Cloudflare DNS-only until smoke passes; `TRUSTED_PROXIES=*` accepted only for loopback-bound edge. Manual operator migrations, no auto-migrate (`docs/DATABASE_MIGRATION_RUNBOOK.md:11-16`).

## 3. Findings

- CI-001 (INFO): pipeline is comprehensive and correctly sequenced; manual dispatch for images/staging is a deliberate safety choice, not a gap.
- CI-002 (LOW, UNVERIFIED): no workflow run was executed in this audit; YAML presence ≠ proven green on current HEAD. `quality.yml` has no least-privilege `permissions:` block (uses defaults).
- CI-003 (LOW): default smoke image tags pinned to May (`staging-20260512-096bc05` in `staging-smoke.yml:9,14`, `GITHUB_ENVIRONMENT.md:70-71`) — stale defaults; update after next proven build.
- DEP-001 (MEDIUM, UNVERIFIED → see stage-12): rollback procedure documented (`docs/ROLLBACK_RUNBOOK.md:51-149`, header "documented but not yet drill-proven"); queue/scheduler supervision examples exist as systemd units but prod supervision unproven; no prod deploy target exists at all (staged delivery stops at staging).

## 4. CI/CD gate verdict

PASS WITH FINDINGS. Code-present and well-designed; operational proof limited to dated staging evidence (2026-05-26 smoke, 2026-05-28 backup). Nothing here blocks Linux migration.
