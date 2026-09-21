#!/usr/bin/env bash

# Runs PHPStan over src/.
#
# src/ names Shopware classes, the plug-in's own classes and _MyParcel-prefixed
# classes from the scoped vendor. Shopware and the PDK cannot live in one
# composer install — the PDK caps symfony/http-foundation at 6, Shopware 6.7
# needs 7.4 — so Shopware is installed on its own in .tmp/shopware and PHPStan
# is pointed at both directories.
#
# PHPStan itself gets its own bare install in .tmp/tools rather than running
# from .tmp/dev-vendor/bin/phpstan: Composer derives an install's autoloader
# class name suffix from composer.lock's content hash, so vendor/ (scoped from
# this plug-in's lock) and .tmp/dev-vendor (installed from the same lock)
# declare the identically-named ComposerAutoloaderInit* class, and PHP refuses
# to declare it twice in one process. A separate composer.json/lock in
# .tmp/tools gets its own hash and its own suffix, and as a side effect keeps
# .tmp/dev-vendor's unscoped Symfony 6 out of PHPStan's own autoloader
# entirely, removing a second, unrelated source of confusing findings.
# .tmp/dev-vendor is not needed here at all any more.

set -Eeuo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SHOPWARE_DIR="${PLUGIN_DIR}/.tmp/shopware"
SHOPWARE_CONSTRAINT='>=v6.7.12.1 <6.8'
TOOLS_DIR="${PLUGIN_DIR}/.tmp/tools"

cd "${PLUGIN_DIR}"

if [[ ! -f vendor/autoload.php ]]; then
  echo "vendor/ is missing. Run 'composer scope' first." >&2
  exit 1
fi

if [[ ! -f "${SHOPWARE_DIR}/vendor/autoload.php" ]]; then
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

if [[ ! -f "${TOOLS_DIR}/vendor/autoload.php" ]]; then
  echo "==> Installing PHPStan for analysis only"
  mkdir -p "${TOOLS_DIR}"
  # Read the version from require-dev so there is one place to bump it.
  PHPSTAN_VERSION="$(php -r 'echo json_decode(file_get_contents("composer.json"), true)["require-dev"]["phpstan/phpstan"];')"
  composer require "phpstan/phpstan:${PHPSTAN_VERSION}" \
    --working-dir="${TOOLS_DIR}" \
    --ignore-platform-req=ext-* \
    --no-interaction \
    --no-progress \
    --no-plugins
fi

php -dmemory_limit=-1 "${TOOLS_DIR}/vendor/bin/phpstan" "$@"
