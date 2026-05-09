#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel)"
cd "$ROOT_DIR"

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

manifest="$tmp_dir/manifest.txt"
archive="$tmp_dir/dz-saas-commerce-clean-export.tar.gz"
extract_dir="$tmp_dir/extract"
failures=0

report_failure() {
  printf 'ERROR: %s\n' "$1" >&2
  failures=$((failures + 1))
}

is_allowed_env_example() {
  case "$1" in
    *.env.example|*.env.testing.example|*.env.production.example|.env.example|.env.testing.example|.env.production.example)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

is_forbidden_export_path() {
  local path="$1"

  case "$path" in
    .git|.git/*|.codex|.codex/*|.cursor|.cursor/*|.vscode|.vscode/*|.idea|.idea/*)
      return 0
      ;;
    .env|*/.env|*.env.local|*/.env.local|*.env.staging|*/.env.staging|*.env.production|*/.env.production|*.env.backup|*/.env.backup)
      if is_allowed_env_example "$path"; then
        return 1
      fi

      return 0
      ;;
    *.pem|*.key|id_rsa|*/id_rsa|id_ed25519|*/id_ed25519|*.p12|*.pfx|auth.json|*/auth.json)
      return 0
      ;;
    backend/vendor|backend/vendor/*|backend/node_modules|backend/node_modules/*|storefront/node_modules|storefront/node_modules/*)
      return 0
      ;;
    storefront/.next|storefront/.next/*|storefront/out|storefront/out/*|storefront/dist|storefront/dist/*|storefront/coverage|storefront/coverage/*|storefront/test-results|storefront/test-results/*|storefront/playwright-report|storefront/playwright-report/*|storefront/*.tsbuildinfo)
      return 0
      ;;
    backend/storage/*.gitignore)
      return 1
      ;;
    backend/public/build|backend/public/build/*|backend/public/hot|backend/public/storage|backend/public/storage/*|backend/storage/*.key|backend/storage/logs/*|backend/storage/pail|backend/storage/pail/*|backend/database/*.sqlite|backend/database/*.sqlite-*|backend/.phpunit.cache|backend/.phpunit.cache/*|backend/.phpunit.result.cache)
      return 0
      ;;
    *.log)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

printf 'Building clean export manifest from tracked and untracked non-ignored files...\n'
git ls-files --cached --others --exclude-standard -z > "$manifest"

if [[ ! -s "$manifest" ]]; then
  report_failure 'export manifest is empty'
fi

printf 'Checking export manifest paths...\n'
while IFS= read -r -d '' path; do
  if is_forbidden_export_path "$path"; then
    report_failure "forbidden path would be exported: $path"
  fi
done < "$manifest"

printf 'Creating temporary clean export archive...\n'
tar --null -czf "$archive" -T "$manifest"

mkdir -p "$extract_dir"
tar -xzf "$archive" -C "$extract_dir"

printf 'Checking extracted archive paths...\n'
while IFS= read -r -d '' path; do
  relative_path="${path#"$extract_dir"/}"

  if is_forbidden_export_path "$relative_path"; then
    report_failure "forbidden path found in export archive: $relative_path"
  fi
done < <(find "$extract_dir" -type f -print0)

printf 'Scanning extracted archive for high-confidence secret patterns...\n'
secret_patterns=(
  '-----BEGIN (RSA |OPENSSH |EC |DSA )?PRIVATE KEY-----'
  'github_pat_[A-Za-z0-9_]{20,}'
  'ghp_[A-Za-z0-9]{20,}'
  'sk-proj-[A-Za-z0-9_-]{20,}'
  'sk-[A-Za-z0-9]{32,}'
  'AKIA[0-9A-Z]{16}'
  'ASIA[0-9A-Z]{16}'
  'xox[baprs]-[A-Za-z0-9-]{10,}'
  'SG\.[A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,}'
  'APP_KEY=base64:[A-Za-z0-9+/=]{32,}'
  'STRIPE_SECRET_KEY=.*sk_live_[A-Za-z0-9]{16,}'
)

for pattern in "${secret_patterns[@]}"; do
  if matches="$(rg -n --hidden --glob '!backend/public/js/filament/**' --glob '!backend/public/css/filament/**' --glob '!*.lock' -e "$pattern" "$extract_dir" || true)" && [[ -n "$matches" ]]; then
    printf '%s\n' "$matches" >&2
    report_failure "export archive contains a high-confidence secret pattern: $pattern"
  fi
done

if (( failures > 0 )); then
  printf 'Clean export check failed with %d issue(s).\n' "$failures" >&2
  exit 1
fi

printf 'Clean export check passed. Archive size: %s\n' "$(du -h "$archive" | awk '{print $1}')"
