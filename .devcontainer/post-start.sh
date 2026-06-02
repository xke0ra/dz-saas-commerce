#!/usr/bin/env bash
set -euo pipefail

cat <<'MSG'

Codespace ready.

Common commands:

Backend:
  cd backend
  php artisan serve --host=0.0.0.0 --port=8000

Queue worker:
  cd backend
  php artisan queue:listen --tries=1 --timeout=0

Storefront:
  cd storefront
  corepack enable
  corepack prepare pnpm@11.1.2 --activate
  pnpm dev --hostname 0.0.0.0 --port 3000

Useful checks:
  bash scripts/dev/backend-test.sh
  bash scripts/dev/storefront-check.sh
  bash scripts/dev/quality-gate.sh

Forwarded ports:
  3000  Storefront Next.js
  8000  Laravel Backend
  7700  Meilisearch
  8025  Mailpit
  9001  MinIO Console

MSG
