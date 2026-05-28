#!/usr/bin/env bash
set -euo pipefail

if [ "${1:-}" != "--confirm-dev-reset" ]; then
  echo "This command resets the Codespaces/local development database only."
  echo "It runs: php artisan migrate:fresh --seed"
  echo ""
  echo "To continue, run:"
  echo "  bash scripts/dev/reset-dev-db.sh --confirm-dev-reset"
  exit 1
fi

cd "$(dirname "$0")/../../backend"

echo "==> Resetting development database"
php artisan migrate:fresh --seed

echo "==> Seeding storefront demo data if StorefrontDemoSeeder exists"
php artisan db:seed --class=StorefrontDemoSeeder --force || true

echo "Development database reset completed."
