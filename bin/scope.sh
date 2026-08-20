#!/usr/bin/env bash

# Produces a scoped vendor directory: every third-party dependency gets the
# _MyParcelNL prefix so the PDK's Symfony 6 and php-di 6 can coexist with the
# Symfony 7 runtime Shopware 6.7 ships. Without this the plugin cannot boot.
#
# Unlike the WooCommerce and PrestaShop plugins, which only scope when building a
# release, this plugin also has to run scoped locally — hence a command rather
# than a CI-only step. Its own src/ stays unscoped: it talks to real Shopware.
#
# Run it from the plugin root, or via `composer scope`.

set -Eeuo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="${PLUGIN_DIR}/.tmp"
SCOPER_DIR="${TMP_DIR}/php-scoper"
SCOPER_STAMP="${SCOPER_DIR}/.installed-for"
SCOPED_DIR="${TMP_DIR}/scoped/vendor"
VENDOR_DIR="${PLUGIN_DIR}/vendor"
BACKUP_DIR="${TMP_DIR}/vendor.previous"

cd "${PLUGIN_DIR}"

# The version lives in require-dev so it is managed like any other dependency.
SCOPER_VERSION="$(php -r 'echo json_decode(file_get_contents("composer.json"), true)["require-dev"]["humbug/php-scoper"];')"
# Reinstall when either the requested version or the PHP running it changed;
# Composer resolves php-scoper's dependencies against the PHP that installed it.
SCOPER_TARGET="${SCOPER_VERSION} on PHP $(php -r 'echo PHP_VERSION;')"

# Until the swap succeeds there is no usable vendor directory. Keep the old one so
# a failure leaves a working plugin behind. On EXIT rather than ERR: the checks
# below bail out with an explicit exit, which an ERR trap never sees.
restore_vendor() {
  if [[ -d "${BACKUP_DIR}" ]]; then
    rm -rf "${VENDOR_DIR}"
    mv "${BACKUP_DIR}" "${VENDOR_DIR}"
    echo "Restored the previous vendor directory." >&2
  fi
}

trap restore_vendor EXIT

# Composer considers an already scoped vendor up to date ("Nothing to install"),
# which would feed php-scoper its own output.
echo "==> Installing dependencies"
mkdir -p "${TMP_DIR}"
rm -rf "${BACKUP_DIR}"
if [[ -d "${VENDOR_DIR}" ]]; then
  mv "${VENDOR_DIR}" "${BACKUP_DIR}"
fi
composer install --no-dev --no-interaction --no-progress

# Installed outside vendor/ because everything in there gets scoped, and a scoped
# scoper cannot scope. That is also why --no-dev above does not lose it.
if [[ ! -f "${SCOPER_STAMP}" ]] || [[ "$(cat "${SCOPER_STAMP}")" != "${SCOPER_TARGET}" ]]; then
  echo "==> Installing php-scoper ${SCOPER_TARGET}"
  rm -rf "${SCOPER_DIR}"
  mkdir -p "${SCOPER_DIR}"
  composer require "humbug/php-scoper:${SCOPER_VERSION}" \
    --working-dir="${SCOPER_DIR}" \
    --ignore-platform-req=ext-* \
    --no-interaction \
    --no-progress
  printf '%s' "${SCOPER_TARGET}" > "${SCOPER_STAMP}"
else
  echo "==> Reusing php-scoper ${SCOPER_TARGET}"
fi

echo "==> Scoping vendor"
rm -rf "${SCOPED_DIR}"
php -d memory_limit=-1 "${SCOPER_DIR}/vendor/bin/php-scoper" add-prefix \
  --config=scoper.vendor.inc.php \
  --output-dir="${SCOPED_DIR}" \
  --force \
  --no-ansi \
  --no-interaction

echo "==> Replacing vendor with the scoped copy"
# php-scoper writes the finder's contents to the output root, so SCOPED_DIR is the
# vendor directory itself rather than a parent holding one.
rm -rf "${VENDOR_DIR}"
mv "${SCOPED_DIR}" "${VENDOR_DIR}"

# The scoped packages declare prefixed namespaces, so the autoload maps from the
# unscoped install no longer match. Optimized but not authoritative, so a new
# class in src/ still resolves via PSR-4 without re-scoping.
echo "==> Dumping autoloader"
composer dump-autoload --no-dev --optimize --no-interaction

# The one thing worth asserting: a silently unprefixed http-foundation would mean
# two Symfony versions in one process, which fails far away from here.
if grep -rq '^namespace Symfony\\Component\\HttpFoundation;' "${VENDOR_DIR}" \
  || ! grep -rq '^namespace _MyParcelNL\\Symfony\\Component\\HttpFoundation;' "${VENDOR_DIR}"; then
  echo "FAILED: Symfony\\Component\\HttpFoundation is not correctly prefixed" >&2
  exit 1
fi

rm -rf "${BACKUP_DIR}"
echo "Done. vendor/ is scoped with the _MyParcelNL prefix."
