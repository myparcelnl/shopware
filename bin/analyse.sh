#!/usr/bin/env bash

# Runs PHPStan over src/.
#
# src/ names Shopware classes, the plug-in's own classes and _MyParcel-prefixed
# classes from the scoped vendor. Shopware and the PDK cannot live in one
# composer install — the PDK caps symfony/http-foundation at 6, Shopware 6.7
# needs 7.4 — so Shopware is installed on its own in .tmp/shopware and PHPStan
# is pointed at both directories.

set -Eeuo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SHOPWARE_DIR="${PLUGIN_DIR}/.tmp/shopware"
SHOPWARE_CONSTRAINT='>=v6.7.12.1 <6.8'

cd "${PLUGIN_DIR}"

if [[ ! -f vendor/autoload.php ]]; then
  echo "vendor/ is missing. Run 'composer scope' first." >&2
  exit 1
fi

bin/dev-vendor.sh

if [[ ! -d "${SHOPWARE_DIR}/vendor" ]]; then
  echo "==> Installing Shopware for analysis only"
  mkdir -p "${SHOPWARE_DIR}"
  # --no-plugins: this install only ever supplies classes for PHPStan to read.
  # Nothing needs a Composer plugin's code to run, and without it symfony/runtime's
  # plugin has no allow-plugins config to be trusted by and aborts the install.
  composer require "shopware/core:${SHOPWARE_CONSTRAINT}" \
    --working-dir="${SHOPWARE_DIR}" \
    --ignore-platform-req=ext-* \
    --no-interaction \
    --no-progress \
    --no-plugins
fi

php -dmemory_limit=-1 .tmp/dev-vendor/bin/phpstan "$@"
