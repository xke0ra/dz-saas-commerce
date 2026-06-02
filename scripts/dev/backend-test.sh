#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../../backend"

echo "==> Composer validate"
composer validate --strict

echo "==> Composer install"
composer install --no-interaction --prefer-dist

echo "==> Composer audit"
composer audit --no-interaction

echo "==> Pint format check"
php vendor/bin/pint --test

echo "==> Prepare testing environment"
cp .env.testing.example .env
php artisan key:generate --ansi

echo "==> Migration smoke"
php artisan migrate:fresh --seed --force

echo "==> Readiness smoke"
php artisan system:health --scope=ready --format=json

echo "==> Backend tests"
php artisan test

echo "==> Route smoke"
php artisan route:list

echo "Backend quality gate passed."
