#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../../storefront"

echo "==> Enable pnpm 11.1.2"
corepack enable
corepack prepare pnpm@11.1.2 --activate

echo "==> Install storefront dependencies"
pnpm install --frozen-lockfile

echo "==> Audit storefront dependencies"
pnpm audit --audit-level moderate

echo "==> Typecheck storefront"
pnpm typecheck

echo "==> Build storefront"
pnpm build

echo "Storefront quality gate passed."
