#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel)"
cd "$ROOT_DIR"

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

is_forbidden_tracked_path() {
  local path="$1"

  case "$path" in
    .env|*/.env|*.env.local|*/.env.local|*.env.staging|*/.env.staging|*.env.production|*/.env.production|*.env.backup|*/.env.backup)
      if is_allowed_env_example "$path"; then
        return 1
      fi

      return 0
      ;;
    *.pem|*.key|id_rsa|*/id_rsa|id_ed25519|*/id_ed25519|*.p12|*.pfx|auth.json|*/auth.json)
      return 0
      ;;
    backend/vendor/*|backend/node_modules/*|storefront/node_modules/*|storefront/.next/*|storefront/test-results/*|storefront/playwright-report/*|backend/database/*.sqlite|backend/database/*.sqlite-*|backend/public/hot)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

printf 'Checking tracked sensitive paths...\n'
while IFS= read -r path; do
  if is_forbidden_tracked_path "$path"; then
    report_failure "tracked sensitive/generated path: $path"
  fi
done < <(git ls-files)

printf 'Checking local sensitive files are ignored...\n'
while IFS= read -r path; do
  if is_allowed_env_example "$path"; then
    continue
  fi

  if git ls-files --error-unmatch "$path" >/dev/null 2>&1; then
    report_failure "local sensitive file is tracked: $path"
    continue
  fi

  if ! git check-ignore -q "$path"; then
    report_failure "local sensitive file is not ignored by gitignore: $path"
  fi
done < <(
  find . \
    \( -path './.git' -o -path './backend/vendor' -o -path './backend/node_modules' -o -path './storefront/node_modules' -o -path './storefront/.next' \) -prune \
    -o -type f \
    \( -name '.env' -o -name '.env.local' -o -name '.env.staging' -o -name '.env.production' -o -name '.env.backup' -o -name '*.pem' -o -name '*.key' -o -name 'id_rsa' -o -name 'id_ed25519' -o -name 'auth.json' \) \
    -print | sed 's#^\./##'
)

printf 'Scanning tracked files for high-confidence secret patterns...\n'
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
  if matches="$(git grep -I -n -E -e "$pattern" -- ':!backend/public/js/filament' ':!backend/public/css/filament' ':!*.lock' || true)" && [[ -n "$matches" ]]; then
    printf '%s\n' "$matches" >&2
    report_failure "tracked files contain a high-confidence secret pattern: $pattern"
  fi
done

if (( failures > 0 )); then
  printf 'Secret hygiene check failed with %d issue(s).\n' "$failures" >&2
  exit 1
fi

printf 'Secret hygiene check passed.\n'
