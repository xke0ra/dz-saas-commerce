#!/usr/bin/env bash
set -euo pipefail

IMAGE_REF="${1:-}"
CACHE_DIR="${2:-${RUNNER_TEMP:-/tmp}/trivy-cache}"
TRIVY_IMAGE="${TRIVY_IMAGE:-aquasec/trivy:0.70.0}"

if [[ -z "${IMAGE_REF}" ]]; then
  echo "Usage: $0 <image-ref> [cache-dir]" >&2
  exit 2
fi

command -v docker >/dev/null 2>&1 || {
  echo "container-image-scan: missing required command: docker" >&2
  exit 1
}

mkdir -p "${CACHE_DIR}"

docker run --rm \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v "${CACHE_DIR}:/root/.cache" \
  "${TRIVY_IMAGE}" image \
    --scanners vuln \
    --ignore-unfixed \
    --pkg-types os,library \
    --severity CRITICAL,HIGH \
    --exit-code 1 \
    --no-progress \
    "${IMAGE_REF}"
