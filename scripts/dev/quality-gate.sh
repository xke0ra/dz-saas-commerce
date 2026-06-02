#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

echo "==> Git whitespace check"
git diff --check

echo "==> Backend quality gate"
bash scripts/dev/backend-test.sh

echo "==> Storefront quality gate"
bash scripts/dev/storefront-check.sh

echo "==> Secret hygiene"
if [ -x scripts/security/secret-hygiene.sh ]; then
  bash scripts/security/secret-hygiene.sh
else
  echo "scripts/security/secret-hygiene.sh not found or not executable; skipping."
fi

echo "==> Clean export check"
if [ -x scripts/release/clean-export-check.sh ]; then
  bash scripts/release/clean-export-check.sh
else
  echo "scripts/release/clean-export-check.sh not found or not executable; skipping."
fi

echo "Full development quality gate passed."
