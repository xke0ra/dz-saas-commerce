# Stage 17: Linux Migration Gate

**Audit Date:** 2026-10-01 | Branch `main` | Commit `bd58620db449ac6211584eb0dc0b125ac3656fb8`

Question: can the developer reinstall/move from Windows/WSL to Linux without losing anything important?

## 1. Verification results

| Check | Result |
|---|---|
| Tracked source on GitHub | 837 files; local HEAD `bd58620` is 1 commit AHEAD of `origin/main` (unpushed: "remove Windows Zone.Identifier artifacts") — MUST PUSH before reinstall |
| Working tree | Clean except untracked `docs/audit/` (this audit's output — new files, must be committed or copied) |
| Ignored but required files (NOT on GitHub, MUST back up) | `backend/.env`, `storefront/.env.local`, `deploy/staging/*.env` (ignored per `.gitignore:14`), `backend/bootstrap/cache/*.php`, dev DB data (see below) |
| Env examples completeness | Complete: `backend/.env.example`, `.env.production.example`, `.env.testing.example`; `storefront/.env.example`, `.env.production.example`; `deploy/staging/*.env.example` + backup env example. No real secrets found in examples (secret-hygiene CI enforced) |
| Local DB disposability | Dev DB `dz_saas_commerce` is disposable: recreated via `migrate:fresh --seed` (CI does exactly this: `quality.yml` backend job). Seeders present (`AlgeriaGeographySeeder` + others). Any hand-entered dev data NOT in seeders will be LOST — export first if needed |
| Docker reproducibility | All images pinned except `minio/minio:latest`, `minio/mc:latest`, `mailpit:latest`, `composer:2` (pulls latest at build; harmless). `postgres:17-alpine`, `redis:7-alpine`, `meilisearch:v1.12`, `node:24-alpine`, `php:8.3-fpm-alpine` pinned. Verified booting on this host |
| Scripts on Linux | All sampled scripts `#!/usr/bin/env bash` + `set -euo pipefail`; no `.bat`/`.ps1`; no Windows paths in code (only advisory `\\wsl.localhost` warnings in docs); `backend/.gitattributes:1` `* text=auto eol=lf`; devcontainer `files.eol \n`. No root `.gitattributes` — consider adding (LOW) |
| Runtimes on current host | PHP 8.3.6 + Composer 2.9.7 present; Node v20.20.2 present but storefront Docker + CI use Node 24 and `pnpm@11.1.2` (packageManager-enforced); global `pnpm` NOT in PATH on this host. Fresh Linux needs Node 24 + pnpm 11.1.2 + PHP 8.3 + Composer + Docker |
| Executable bits / CRLF | Not directly verifiable with available read tooling (UNVERIFIED); mitigated by `.gitattributes` + `env bash` shebangs; re-check with `git ls-files -s` + `file` after fresh clone |

## 2. Verdict

**SAFE WITH REQUIRED PRE-MIGRATION ACTIONS.** No blockers; the repository is clone-complete. Required actions (checklist in `LINUX_MIGRATION_READINESS.md`): push the 1 ahead commit; back up `backend/.env`, `storefront/.env.local`, any `deploy/staging/*.env`; export irreplaceable dev-DB rows (or accept reseed); commit or copy `docs/audit/`; on Linux install Docker, PHP 8.3, Composer, Node 24, pnpm 11.1.2; `docker compose up -d`, copy env examples, `migrate --seed`, verify with Pint/tsc/tests.
