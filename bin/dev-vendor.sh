#!/usr/bin/env bash

# Builds an unscoped development install in .tmp/dev-vendor.
#
# vendor/ holds the scoped, production-only dependencies the plug-in needs to
# boot; replacing it with a development install breaks the running shop. Pest,
# PHPStan and the PDK's test utilities therefore live in a second vendor
# directory, which COMPOSER_VENDOR_DIR puts wherever we want.
#
# Idempotent: it reinstalls only when composer.json or composer.lock changed.

set -Eeuo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEV_VENDOR_DIR="${PLUGIN_DIR}/.tmp/dev-vendor"
STAMP="${DEV_VENDOR_DIR}/.installed-for"

cd "${PLUGIN_DIR}"

# php -r rather than shasum: the Shopware image this also has to run in does
# not ship shasum, but it is nothing without PHP.
TARGET="$(php -r '
$contents = "";
foreach (["composer.json", "composer.lock"] as $file) {
    if (is_file($file)) {
        $contents .= file_get_contents($file);
    }
}
echo hash("sha256", $contents);
')"

if [[ -f "${STAMP}" ]] && [[ "$(cat "${STAMP}")" == "${TARGET}" ]]; then
  exit 0
fi

echo "==> Installing development dependencies in .tmp/dev-vendor"
COMPOSER_VENDOR_DIR="${DEV_VENDOR_DIR}" composer install \
  --no-interaction \
  --no-progress

printf '%s' "${TARGET}" > "${STAMP}"
