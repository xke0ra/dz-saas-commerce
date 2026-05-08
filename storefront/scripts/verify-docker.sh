#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

NODE_IMAGE="${STOREFRONT_NODE_IMAGE:-node:24-bookworm}"
PLAYWRIGHT_IMAGE="${STOREFRONT_PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.59.1-noble}"
PNPM_VERSION="${STOREFRONT_PNPM_VERSION:-10.33.2}"

DOCKER_USER_ARGS=()
if command -v id >/dev/null 2>&1; then
  DOCKER_USER_ARGS=(--user "$(id -u):$(id -g)")
fi

COMMON_ENV=(
  -e CI=true
  -e NEXT_TELEMETRY_DISABLED=1
  -e HOME=/tmp
  -e COREPACK_HOME=/tmp/corepack
  -e PNPM_HOME=/tmp/pnpm
  -e PNPM_STORE_DIR=/tmp/pnpm-store
  -e PATH=/tmp/pnpm:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
)

run_in_image() {
  local image="$1"
  local command="$2"

  docker run --rm \
    -v "${APP_DIR}:/app" \
    -w /app \
    "${DOCKER_USER_ARGS[@]}" \
    "${COMMON_ENV[@]}" \
    "${image}" \
    bash -c "mkdir -p /tmp/pnpm /tmp/corepack /tmp/pnpm-store; corepack enable --install-directory /tmp/pnpm; corepack prepare pnpm@${PNPM_VERSION} --activate; pnpm config set store-dir /tmp/pnpm-store; ${command}"
}

case "${1:-all}" in
  install)
    run_in_image "${NODE_IMAGE}" "node --version; pnpm --version; pnpm install --frozen-lockfile"
    ;;
  typecheck)
    run_in_image "${NODE_IMAGE}" "pnpm typecheck"
    ;;
  build)
    run_in_image "${NODE_IMAGE}" "pnpm build"
    ;;
  e2e)
    run_in_image "${PLAYWRIGHT_IMAGE}" "pnpm install --frozen-lockfile; pnpm test:e2e"
    ;;
  smoke)
    run_in_image "${NODE_IMAGE}" "pnpm typecheck; pnpm build"
    ;;
  all)
    run_in_image "${NODE_IMAGE}" "node --version; pnpm --version; pnpm install --frozen-lockfile; pnpm typecheck; pnpm build"
    run_in_image "${PLAYWRIGHT_IMAGE}" "pnpm install --frozen-lockfile; pnpm test:e2e"
    ;;
  *)
    echo "Usage: $0 [install|typecheck|build|e2e|smoke|all]" >&2
    exit 2
    ;;
esac
