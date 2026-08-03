#!/usr/bin/env bash

set -euo pipefail

PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="ramzpal-payment-gateway-for-woocommerce"
VERSION="$(sed -n 's/^ \* Version: \(.*\)$/\1/p' "${PLUGIN_ROOT}/ramzpal-payment-gateway-for-woocommerce.php" | head -n 1)"

if [[ -z "${VERSION}" ]]; then
  echo "Could not read plugin version." >&2
  exit 1
fi

BUILD_ROOT="$(mktemp -d)"
PACKAGE_DIR="${BUILD_ROOT}/${PLUGIN_SLUG}"
OUTPUT_DIR="${PLUGIN_ROOT}/dist"
OUTPUT_FILE="${OUTPUT_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

cleanup() {
  rm -rf "${BUILD_ROOT}"
}
trap cleanup EXIT

mkdir -p "${PACKAGE_DIR}" "${OUTPUT_DIR}"
rsync -a --exclude-from="${PLUGIN_ROOT}/.distignore" "${PLUGIN_ROOT}/" "${PACKAGE_DIR}/"

find "${PACKAGE_DIR}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

rm -f "${OUTPUT_FILE}"
(
  cd "${BUILD_ROOT}"
  zip -q -r "${OUTPUT_FILE}" "${PLUGIN_SLUG}"
)

echo "Built ${OUTPUT_FILE}"
