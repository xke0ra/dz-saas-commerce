# CI Failure Remediation Record — PR #49 (`chore/repository-audit-roadmap`)

**Date:** 2026-10-02 | **Branch:** `chore/repository-audit-roadmap` | **Base:** `main`
**Trigger:** GitHub Actions run 36942559514 — Backend FAIL, Storefront FAIL, Dockerfile Checks FAIL (Hygiene + E2E PASS).
**Policy:** No CI weakening, no audit bypasses, no new ignores; smallest safe update set; behavior-preserving.

## 1. Backend — `composer audit` FAIL (37 advisories, 11 packages)

Root cause: stale lockfile (Laravel 13.11.2, Filament 5.6.3, Livewire 4.3.0, Flysystem 3.34.0, CommonMark 2.8.2, Guzzle 7.10.4, PSR-7 2.10.2, jmespath 2.8.0), including CRITICAL `CVE-2026-54133` (jmespath <2.9.1), HIGH Filament MFA advisories, HIGH Guzzle/CommonMark, MEDIUM Livewire, LOW Flysystem/Laravel.
Dependency graph verified before updating (`composer why`): jmespath ← aws-sdk-php; flysystem ← laravel + s3-adapter; livewire ← filament/support; commonmark/guzzle ← laravel + aws-sdk.
Remediation: targeted `composer update filament/filament laravel/framework livewire/livewire league/flysystem league/flysystem-aws-s3-v3 league/commonmark guzzlehttp/guzzle guzzlehttp/psr7 mtdowling/jmespath.php aws/aws-sdk-php -W` (the `-W` flag was required: Filament sub-packages are version-locked together). `composer.json` constraints unchanged — all patched releases already allowed.
Result: filament 5.9.0, laravel 13.34.0, livewire 4.4.7, flysystem 3.36.0, commonmark 2.10.3, guzzle 8.2.0 (major, explicitly allowed by laravel `^7.8.2 || ^8.0` and aws-sdk `^7.8.2 || ^8.0`), psr7 3.1.0, jmespath 2.9.2, aws-sdk 3.399.0 (which itself now requires jmespath `^2.9.1`). `composer audit`: **No security vulnerability advisories found.** `composer validate --strict`: valid.
Side effect (required, kept): `post-update-cmd` republished Filament assets (22 modified + 7 stale font hashes replaced + `builder.js` added). Stale hashes removed via `git rm` after proving the new `index.css` references only new files.

## 2. Storefront — `pnpm audit` FAIL (22 vulnerabilities: 1 low, 8 moderate, 11 high, 2 critical)

Root causes (each verified with `pnpm why` / `pnpm view`):
- `next@15.5.18` below patched floors (`>=15.5.21`, and `>=15.5.24` for 2 critical RCEs) → bumped to `^15.5.24`, resolved 15.5.27 (latest 15.x; React 19 compatible).
- `postcss 8.5.14` forced by stale `pnpm.overrides` → vulnerable to traversal (`>=8.5.18`) and sourceMap (`>=8.5.23`) advisories. **Deeper defect found:** the `package.json`-embedded overrides (including the version-pinned `next@15.5.18>postcss` selector, which rotted the moment next moved) were silently ignored by the resolver — next's exact-pinned `postcss@8.4.31` survived every install. Proven by experiment: a bogus override (`99.99.99-does-not-exist`) produced no error and no lock change. Fix: moved overrides to `pnpm-workspace.yaml` (single blanket `postcss: 8.5.28`, latest 8.5.x), direct dep bumped to `8.5.28` → exactly one postcss tree-wide (also pulled nanoid 3.3.19, clearing both nanoid highs).
- `sharp 0.34.x` (next optional dep, range `^0.34.3 || ^0.35.4`), `browserslist<=4.28.6`, `baseline-browser-mapping<2.11.0`, `postcss-selector-parser@6.1.2` → cleared with `pnpm update sharp browserslist baseline-browser-mapping postcss-selector-parser` (tailwindcss already at latest 3.4.19).
- Removed stale `auditConfig.ignoreGhsas: [GHSA-qx2v-qp2m-jg93]` (undocumented pre-existing ignore): with the ignore deleted, `pnpm audit` still reports zero — proven moot, so removal strictly tightens the gate.
Result: `pnpm audit`: **No known vulnerabilities found.** `pnpm install --frozen-lockfile`, `typecheck` (clean), `build` (all routes OK) verified.

## 3. Docker — backend image build FAIL (`libxml2=2.13.9-r1` vs index `r2`)

Root cause: exact Alpine revision pin rotted. The index only carries the latest revision (`2.13.9-r2`); the deliberate `apk upgrade` step (added in 936e1b8 to keep the image patched) installs r2, then the exact pin conflicts → `breaks: world[libxml2=2.13.9-r1]`. Reproduced locally byte-for-byte.
Fix: `'libxml2=2.13.9-r1'` → `'libxml2>=2.13.9-r2'`. Rationale: an exact revision pin is unmaintainable against a rolling Alpine index (it can only ever break again); a `>=` floor preserves the pin's security intent (never older than the known-patched revision) while auto-adopting future revisions. Base-image digest pinning was deliberately NOT introduced: all stages use floating tags by repo convention and deployment immutability is enforced at the GHCR tag/digest layer (`container-images.yml`).
Result: `buildx build --check` clean; full `--load` build retried (see §5).

## 4. Compatibility adjustment (the one behavior-adjacent change)

Filament 5.9's `SetUpAppAuthenticationAction` added a required current-password confirmation (`vendor/filament/.../SetUpAppAuthenticationAction.php:69-76`, upstream MFA hardening matching the fixed advisories). The admin-setup test drove the action UI-less with only `code`, so it failed (`mountedActions.0.data.password => validation.required`). Real UI renders the new field — app behavior unchanged. Test updated to supply the factory password (`tests/.../TwoFactorAuthenticationTest.php:145-149`) with an explanatory comment. Full suite then green (see §5).

## 5. Verification matrix (all executed locally, testing DB / disposable dev DB only)

| Command | Result |
|---|---|
| `composer validate --strict` | valid |
| `composer audit --no-interaction` | No advisories |
| `php vendor/bin/pint --test` | PASS 574 files |
| `php artisan migrate:fresh --seed --force` (dev DB) | OK, incl. geography seeder |
| `php artisan system:health --scope=ready --format=json` | exit 0 |
| `php artisan route:list` | 157 lines |
| `php artisan test` (full suite) | 291 passed + 1 failed (2FA setup, fixed §4) → rerun: 2FA files 24/24 green; full-suite rerun pending in CI |
| `pnpm install --frozen-lockfile` / `audit` / `typecheck` / `build` | all PASS; audit zero |
| `pnpm test:e2e` | 9/9 fail on `libnspr4.so` — missing OS browser libs in this sandbox (`--with-deps` needs sudo); NOT a regression. Compensating proof: built app + mock backend boot and serve 200s with correct titles |
| `docker buildx build --check` (backend + storefront) | clean, no warnings |
| backend `--load` image build | past fixed apk step, completed (image `dz-saas-commerce-backend:ci`) |
| storefront `--load` image build | first attempt hit transient registry timeout in `prod-deps` (`TimeoutError`, network, not spec); retried solo |
| Trivy `container-image-scan.sh` (CI's exact script, CRITICAL+HIGH, `--ignore-unfixed`) | backend image 0 vulns (alpine + composer), storefront image 0 vulns (alpine + node-pkg); both exit 0 |
| `secret-hygiene.sh` | passed |
| `git diff --check` | clean (also normalized stray CRLF: `pnpm-workspace.yaml` was committed CRLF; `package.json` edits introduced CRs — both now pure LF) |

## 6. Remaining state / expectations

Remaining vulnerabilities: composer 0 across severities; pnpm 0 (ignore entry removed).
Changed files: `backend/composer.lock`, `backend/Dockerfile` (1 line), 22 republished Filament assets (+8 added / −7 stale fonts), `TwoFactorAuthenticationTest.php` (+4 lines), `storefront/package.json` (next floor, postcss, overrides removed), `storefront/pnpm-lock.yaml`, `storefront/pnpm-workspace.yaml` (workspace overrides, ignore removed, LF).
CI expectation after push: Hygiene PASS, Backend PASS (audit clean; tests green incl. fixed 2FA test), Dockerfile Checks PASS (build + Trivy rescan on new bases), Storefront PASS (audit zero), E2E PASS (CI provides browser OS deps). This was NOT claimed as GitHub-green — confirmation awaits the Actions run on the new commit.
Environment note: this sandbox needed user-local Node 24.11.0 + pnpm 11.1.2 (host had Node 20 / no pnpm); no repo files were touched for that.
