#!/usr/bin/env bash

# Produces a scoped vendor directory: every third-party dependency gets the
# _MyParcelNL prefix so the PDK's Symfony 6 and php-di 6 can coexist with the
# Symfony 7 runtime Shopware 6.7 ships. Without this the plugin cannot boot.
#
# Run it from the plugin root, or via `composer scope`. The plugin's own src/
# is deliberately left alone — it talks to the real Shopware classes.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="${PLUGIN_DIR}/.tmp"
SCOPER_DIR="${TMP_DIR}/php-scoper"
SCOPED_DIR="${TMP_DIR}/scoped/vendor"
SCOPER_VERSION="${PHP_SCOPER_VERSION:-^0.18.0}"

cd "${PLUGIN_DIR}"

# Start from a clean unscoped install every time. Composer considers an already
# scoped vendor up to date ("Nothing to install"), which would feed php-scoper
# its own output. It happens to guard against re-prefixing, but relying on that
# makes the outcome depend on the state we started in.
echo "==> Installing dependencies"
rm -rf "${PLUGIN_DIR}/vendor"
composer install --no-dev --no-interaction --no-progress

# php-scoper lives outside the plugin vendor on purpose: anything inside
# vendor/ ends up scoped, and a scoped scoper cannot scope.
if [[ ! -f "${SCOPER_DIR}/vendor/bin/php-scoper" ]]; then
  echo "==> Installing php-scoper ${SCOPER_VERSION}"
  mkdir -p "${SCOPER_DIR}"
  composer require "humbug/php-scoper:${SCOPER_VERSION}" \
    --working-dir="${SCOPER_DIR}" \
    --ignore-platform-req=ext-* \
    --no-interaction \
    --no-progress
else
  echo "==> Reusing php-scoper from ${SCOPER_DIR#"${PLUGIN_DIR}/"}"
fi

echo "==> Scoping vendor"
rm -rf "${SCOPED_DIR}"
php -d memory_limit=-1 "${SCOPER_DIR}/vendor/bin/php-scoper" add-prefix \
  --config=scoper.vendor.inc.php \
  --output-dir="${SCOPED_DIR}" \
  --force \
  --no-ansi \
  --no-interaction

# php-scoper writes the finder's contents to the output root, so SCOPED_DIR is
# the vendor directory itself rather than a parent holding one.
if [[ ! -f "${SCOPED_DIR}/autoload.php" ]]; then
  echo "FAILED: php-scoper produced no autoload.php in ${SCOPED_DIR#"${PLUGIN_DIR}/"}" >&2
  exit 1
fi

echo "==> Replacing vendor with the scoped copy"
rm -rf "${PLUGIN_DIR}/vendor"
mv "${SCOPED_DIR}" "${PLUGIN_DIR}/vendor"

# The scoped packages declare prefixed namespaces, so the autoload maps that
# came out of the unscoped install no longer match. Optimized but not
# authoritative: a new class in src/ should still resolve via PSR-4 without
# having to re-scope.
echo "==> Dumping autoloader"
composer dump-autoload --no-dev --optimize --no-interaction

echo "==> Verifying"
if grep -rq '^namespace Symfony\\Component\\HttpFoundation' "${PLUGIN_DIR}/vendor"; then
  echo "FAILED: unprefixed Symfony\\Component\\HttpFoundation left in vendor" >&2
  exit 1
fi

if ! grep -rq '^namespace _MyParcelNL\\Symfony\\Component\\HttpFoundation' "${PLUGIN_DIR}/vendor"; then
  echo "FAILED: no prefixed Symfony\\Component\\HttpFoundation found in vendor" >&2
  exit 1
fi

if grep -rq 'function [a-zA-Z_]*(string \$className = null' "${PLUGIN_DIR}/vendor/php-di"; then
  echo "FAILED: php-di still has implicitly nullable parameters (PHP 8.4 deprecation)" >&2
  exit 1
fi

echo "Done. vendor/ is scoped with the _MyParcelNL prefix."
