#!/usr/bin/env bash
set -euo pipefail

ROOT="/workspaces/dz-saas-commerce"

echo "==> Entering workspace"
cd "$ROOT"

echo "==> Installing backend dependencies"
cd "$ROOT/backend"
composer install --no-interaction --prefer-dist

if [ ! -f .env ]; then
  cp .env.example .env
fi

echo "==> Preparing backend .env for Codespaces"
python3 - <<'PY'
from pathlib import Path

path = Path(".env")
env = path.read_text()

values = {
    "APP_ENV": "local",
    "APP_DEBUG": "true",
    "APP_URL": "http://localhost:8000",
    "TRUSTED_PROXIES": "*",

    "DB_CONNECTION": "pgsql",
    "DB_HOST": "postgres",
    "DB_PORT": "5432",
    "DB_DATABASE": "dz_saas_commerce",
    "DB_USERNAME": "dz_user",
    "DB_PASSWORD": "dz_password",

    "SESSION_DRIVER": "database",
    "QUEUE_CONNECTION": "database",
    "CACHE_STORE": "database",

    "SCOUT_DRIVER": "meilisearch",
    "SCOUT_QUEUE": "true",
    "MEILISEARCH_HOST": "http://meilisearch:7700",
    "MEILISEARCH_KEY": "dz_meili_master_key",

    "REDIS_CLIENT": "phpredis",
    "REDIS_HOST": "redis",
    "REDIS_PASSWORD": "null",
    "REDIS_PORT": "6379",

    "MAIL_MAILER": "smtp",
    "MAIL_HOST": "mailpit",
    "MAIL_PORT": "1025",

    "FILESYSTEM_DISK": "local",
    "PRODUCT_IMAGES_DISK": "public",

    "AWS_ACCESS_KEY_ID": "dz_minio",
    "AWS_SECRET_ACCESS_KEY": "dz_minio_password",
    "AWS_DEFAULT_REGION": "us-east-1",
    "AWS_BUCKET": "dz-saas-commerce-local",
    "AWS_URL": "http://localhost:9000/dz-saas-commerce-local",
    "AWS_ENDPOINT": "http://minio:9000",
    "AWS_USE_PATH_STYLE_ENDPOINT": "true",
}

lines = env.splitlines()
seen = set()
new_lines = []

for line in lines:
    if "=" not in line or line.lstrip().startswith("#"):
        new_lines.append(line)
        continue

    key = line.split("=", 1)[0]
    if key in values:
        new_lines.append(f"{key}={values[key]}")
        seen.add(key)
    else:
        new_lines.append(line)

for key, value in values.items():
    if key not in seen:
        new_lines.append(f"{key}={value}")

path.write_text("\n".join(new_lines) + "\n")
PY

php artisan key:generate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "==> Waiting for PostgreSQL"
until pg_isready -h postgres -p 5432 -U dz_user -d dz_saas_commerce; do
  sleep 2
done

echo "==> Running backend migrations"
php artisan migrate --force

echo "==> Seeding demo data if StorefrontDemoSeeder exists"
php artisan db:seed --class=StorefrontDemoSeeder --force || true

echo "==> Installing storefront dependencies"
cd "$ROOT/storefront"
corepack enable
corepack prepare pnpm@11.1.2 --activate
pnpm install --frozen-lockfile

echo "==> Codespaces post-create completed"
