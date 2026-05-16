#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
STAGING_DIR="${ROOT_DIR}/deploy/staging"

IMAGES_ENV="${IMAGES_ENV:-${STAGING_DIR}/images.env}"
COMPOSE_FILE="${COMPOSE_FILE:-${STAGING_DIR}/docker-compose.staging.example.yml}"
COMPOSE_FILES="${COMPOSE_FILES:-${COMPOSE_FILE}}"
COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-dz-saas-staging}"
ACTION="${1:-validate}"

PLACEHOLDER_PATTERN='replace_with|replace_me|sha-replace|owner/repo|example\.com|base64:replace'

die() {
  echo "staging-smoke: $*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || die "missing required command: $1"
}

require_file() {
  [[ -f "$1" ]] || die "missing required file: $1"
}

resolve_staging_path() {
  local path="$1"
  if [[ "${path}" = /* ]]; then
    printf '%s\n' "${path}"
  else
    printf '%s/%s\n' "${STAGING_DIR}" "${path}"
  fi
}

load_images_env() {
  require_file "${IMAGES_ENV}"
  set -a
  # shellcheck disable=SC1090
  source "${IMAGES_ENV}"
  set +a
}

check_no_placeholders() {
  local file="$1"

  if grep -Eq "${PLACEHOLDER_PATTERN}" "${file}"; then
    local line
    local content
    local key

    while IFS=: read -r line content; do
      key="${content%%=*}"
      key="${key#"${key%%[![:space:]]*}"}"

      if [[ "${key}" == "${content}" || -z "${key}" ]]; then
        key="redacted"
      fi

      echo "staging-smoke: placeholder in ${file}:${line} (${key})" >&2
    done < <(grep -En "${PLACEHOLDER_PATTERN}" "${file}")

    die "${file} still contains placeholder values"
  fi
}

validate_image_ref() {
  local name="$1"
  local value="$2"

  [[ -n "${value}" ]] || die "${name} is empty"
  [[ ! "${value}" =~ (replace_me|sha-replace|owner/repo) ]] || die "${name} contains a placeholder: ${value}"
  [[ ! "${value}" =~ :(latest|staging|production)$ ]] || die "${name} must use an immutable tag or digest, not ${value}"

  if [[ "${value}" =~ @sha256:[0-9a-f]{64}$ ]]; then
    return 0
  fi

  if [[ "${value}" =~ :(sha-[0-9a-f]{7,}|staging-[0-9]{8}-[A-Za-z0-9_.-]+|v[0-9][A-Za-z0-9_.-]*)$ ]]; then
    return 0
  fi

  die "${name} must use @sha256, sha-<commit>, staging-YYYYMMDD-<id>, or version tag: ${value}"
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

curl_smoke() {
  local output="$1"
  shift

  curl \
    --connect-timeout 5 \
    --max-time 20 \
    "$@" \
    > "${output}"
}

check_failed_jobs() {
  local output="/tmp/dz-saas-staging-failed-jobs.txt"

  compose_cmd exec -T backend php artisan queue:failed > "${output}"

  if ! grep -q "No failed jobs found" "${output}"; then
    die "failed jobs were reported; inspect ${output} on the runner"
  fi

  echo "staging-smoke: failed-job check saved to ${output}"
}

validate() {
  require_command docker
  local compose_file
  IFS=':' read -r -a compose_files <<< "${COMPOSE_FILES}"
  for compose_file in "${compose_files[@]}"; do
    [[ -n "${compose_file}" ]] || continue
    require_file "${compose_file}"
  done

  load_images_env

  : "${BACKEND_IMAGE:=}"
  : "${STOREFRONT_IMAGE:=}"
  : "${BACKEND_ENV_FILE:=./backend.env}"
  : "${STOREFRONT_ENV_FILE:=./storefront.env}"

  validate_image_ref BACKEND_IMAGE "${BACKEND_IMAGE}"
  validate_image_ref STOREFRONT_IMAGE "${STOREFRONT_IMAGE}"

  local backend_env
  local storefront_env
  backend_env="$(resolve_staging_path "${BACKEND_ENV_FILE}")"
  storefront_env="$(resolve_staging_path "${STOREFRONT_ENV_FILE}")"

  require_file "${backend_env}"
  require_file "${storefront_env}"
  check_no_placeholders "${IMAGES_ENV}"
  check_no_placeholders "${backend_env}"
  check_no_placeholders "${storefront_env}"

  compose_cmd config >/tmp/dz-saas-staging-compose.yml
  echo "staging-smoke: compose config validated at /tmp/dz-saas-staging-compose.yml"
}

pull_images() {
  validate
  compose_cmd pull
}

start_stack() {
  validate
  compose_cmd up -d --remove-orphans
}

verify_stack() {
  require_command curl
  validate

  local edge_port="${EDGE_PORT:-8080}"
  local edge_url="${STAGING_EDGE_URL:-http://127.0.0.1:${edge_port}}"
  local backend_host="${STAGING_BACKEND_HOST:-api.example.com}"
  local storefront_host="${STAGING_STOREFRONT_HOST:-example.com}"

  compose_cmd ps
  compose_cmd exec -T backend php artisan system:health --scope=ready --format=json
  check_failed_jobs

  curl_smoke /tmp/dz-saas-staging-storefront.headers -fsSIL -H "Host: ${storefront_host}" "${edge_url}/"
  curl_smoke /tmp/dz-saas-staging-live.json -fsS -H "Host: ${backend_host}" "${edge_url}/api/system/health/live"
  curl_smoke /tmp/dz-saas-staging-ready.json -fsS -H "Host: ${backend_host}" "${edge_url}/api/system/health/ready"

  echo "staging-smoke: storefront headers saved to /tmp/dz-saas-staging-storefront.headers"
  echo "staging-smoke: backend health saved to /tmp/dz-saas-staging-live.json and /tmp/dz-saas-staging-ready.json"
}

case "${ACTION}" in
  validate)
    validate
    ;;
  pull)
    pull_images
    ;;
  up)
    start_stack
    ;;
  verify)
    verify_stack
    ;;
  all)
    pull_images
    start_stack
    verify_stack
    ;;
  down)
    require_command docker
    require_file "${IMAGES_ENV}"
    load_images_env
    compose_cmd down
    ;;
  *)
    echo "Usage: $0 [validate|pull|up|verify|all|down]" >&2
    exit 2
    ;;
esac
