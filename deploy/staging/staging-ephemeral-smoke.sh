#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
STAGING_DIR="${ROOT_DIR}/deploy/staging"
TMP_DIR_CREATED=0
if [[ -n "${STAGING_EPHEMERAL_TMP_DIR:-}" ]]; then
  TMP_DIR="${STAGING_EPHEMERAL_TMP_DIR}"
else
  TMP_DIR="$(mktemp -d)"
  TMP_DIR_CREATED=1
fi
ACTION="${1:-all}"

BACKEND_IMAGE="${BACKEND_IMAGE:-}"
STOREFRONT_IMAGE="${STOREFRONT_IMAGE:-ghcr.io/xke0ra/dz-saas-commerce/storefront:staging-20260512-a1e913d}"
EDGE_PORT="${EDGE_PORT:-18080}"
COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-dz-saas-staging-ephemeral}"
COMPOSE_FILES="${STAGING_DIR}/docker-compose.staging.example.yml:${STAGING_DIR}/docker-compose.staging.ephemeral.yml"
IMAGES_ENV="${TMP_DIR}/images.env"
BACKEND_ENV_FILE="${TMP_DIR}/backend.env"
STOREFRONT_ENV_FILE="${TMP_DIR}/storefront.env"

die() {
  echo "staging-ephemeral-smoke: $*" >&2
  exit 1
}

random_app_key() {
  if command -v openssl >/dev/null 2>&1; then
    openssl rand -base64 32
    return
  fi

  head -c 32 /dev/urandom | base64
}

compose_cmd() {
  local compose_file_args=()
  local compose_file

  IFS=':' read -r -a compose_files <<< "${COMPOSE_FILES}"
  for compose_file in "${compose_files[@]}"; do
    [[ -n "${compose_file}" ]] || continue
    compose_file_args+=(-f "${compose_file}")
  done

  docker compose \
    --project-name "${COMPOSE_PROJECT_NAME}" \
    --env-file "${IMAGES_ENV}" \
    "${compose_file_args[@]}" \
    "$@"
}

render_env_files() {
  mkdir -p "${TMP_DIR}"

  cat > "${IMAGES_ENV}" <<EOF
BACKEND_IMAGE=${BACKEND_IMAGE}
STOREFRONT_IMAGE=${STOREFRONT_IMAGE}
BACKEND_ENV_FILE=${BACKEND_ENV_FILE}
STOREFRONT_ENV_FILE=${STOREFRONT_ENV_FILE}
EDGE_PORT=${EDGE_PORT}
STAGING_EDGE_URL=http://127.0.0.1:${EDGE_PORT}
STAGING_BACKEND_HOST=api.staging.internal
STAGING_STOREFRONT_HOST=staging.internal
EOF

  cat > "${BACKEND_ENV_FILE}" <<EOF
APP_NAME="DZ SaaS Commerce"
APP_ENV=staging
APP_KEY=base64:$(random_app_key)
APP_DEBUG=false
APP_URL=http://api.staging.internal:8080
TRUSTED_PROXIES=172.16.0.0/12

APP_LOCALE=ar
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=cache
APP_MAINTENANCE_STORE=redis
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=dz_saas_staging
DB_USERNAME=dz_user
DB_PASSWORD=dz_password
DB_SSLMODE=prefer

BROADCAST_CONNECTION=log
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax

FILESYSTEM_DISK=s3
PRODUCT_IMAGES_DISK=s3

SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
SCOUT_PREFIX=ephemeral_
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=dz_meili_master_key

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=dz_redis_password
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=no-reply@staging.internal
MAIL_FROM_NAME="DZ SaaS Commerce"

AWS_ACCESS_KEY_ID=dz_minio
AWS_SECRET_ACCESS_KEY=dz_minio_password
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=dz-saas-commerce-staging
AWS_URL=http://api.staging.internal:8080/storage
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

VITE_APP_NAME="DZ SaaS Commerce"
EOF

  cat > "${STOREFRONT_ENV_FILE}" <<EOF
NODE_ENV=production
NEXT_PUBLIC_API_BASE_URL=http://api.staging.internal:8080
NEXT_PUBLIC_ASSET_BASE_URL=http://api.staging.internal:8080
NEXT_PUBLIC_STOREFRONT_BASE_URL=http://staging.internal:8080
STOREFRONT_BASE_URL=http://staging.internal:8080

NEXT_PUBLIC_DEFAULT_STORE=demo-store
DEFAULT_STORE_IDENTIFIER=demo-store
EOF
}

run_smoke() {
  IMAGES_ENV="${IMAGES_ENV}" \
  COMPOSE_FILES="${COMPOSE_FILES}" \
  COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME}" \
    "${STAGING_DIR}/staging-smoke.sh" "$1"
}

prepare_backend() {
  compose_cmd exec -T backend php artisan migrate --force
  compose_cmd exec -T backend php artisan db:seed --class=StorefrontDemoSeeder --force
}

cleanup() {
  local exit_code=$?

  if [[ "${KEEP_STAGING_EPHEMERAL:-0}" != "1" ]]; then
    compose_cmd down -v --remove-orphans >/dev/null 2>&1 || true
  fi

  if [[ "${TMP_DIR_CREATED}" == "1" && "${KEEP_STAGING_EPHEMERAL:-0}" != "1" ]]; then
    rm -rf "${TMP_DIR}"
  fi

  exit "${exit_code}"
}

cleanup_tmp() {
  if [[ "${TMP_DIR_CREATED}" == "1" ]]; then
    rm -rf "${TMP_DIR}"
  fi
}

case "${ACTION}" in
  validate)
    render_env_files
    run_smoke validate
    cleanup_tmp
    ;;
  pull)
    render_env_files
    run_smoke pull
    cleanup_tmp
    ;;
  up)
    render_env_files
    run_smoke up
    prepare_backend
    cleanup_tmp
    ;;
  verify)
    render_env_files
    run_smoke verify
    cleanup_tmp
    ;;
  all)
    trap cleanup EXIT
    render_env_files
    if [[ "${SKIP_PULL:-0}" != "1" ]]; then
      run_smoke pull
    else
      run_smoke validate
    fi
    run_smoke up
    prepare_backend
    run_smoke verify
    ;;
  down)
    if [[ -z "${BACKEND_IMAGE}" ]]; then
      BACKEND_IMAGE="ghcr.io/xke0ra/dz-saas-commerce/backend:staging-19700101-down"
    fi
    render_env_files
    compose_cmd down -v --remove-orphans
    cleanup_tmp
    ;;
  *)
    die "supported actions: validate, pull, up, verify, all, down"
    ;;
esac
