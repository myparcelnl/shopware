#!/usr/bin/env bash

# Installs Shopware on its own in .tmp/shopware, for PHPStan and for the unit tests.
#
# Shopware and the PDK cannot live in one composer install — the PDK caps
# symfony/http-foundation at 6, Shopware 6.7 needs 7.4 — so neither vendor/ nor
# .tmp/dev-vendor can hold it. bin/analyse.sh points PHPStan at this install, and
# tests/Pest.php autoloads the Shopware classes the tests build from it.
#
# Idempotent: it reinstalls only when the stamp does not match.

set -Eeuo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SHOPWARE_DIR="${PLUGIN_DIR}/.tmp/shopware"
SHOPWARE_CONSTRAINT='>=v6.7.12.1 <6.8'
SHOPWARE_STAMP="${SHOPWARE_DIR}/.installed-for"

cd "${PLUGIN_DIR}"

# The install carries a stamp, like the one bin/scope.sh gives php-scoper, and
# for the same reason. CI starts from an empty .tmp and picks a bumped constraint
# up on its own; a developer keeps whatever was installed first, so a gate on the
# directory alone means local green against CI red with nothing on screen to
# explain it. The PHP version is part of the stamp because Composer resolves an
# install's dependencies against the PHP that ran it.
PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
SHOPWARE_TARGET="${SHOPWARE_CONSTRAINT} on PHP ${PHP_VERSION}"

# autoload.php as well as the stamp: the stamp is only written once the install
# succeeded, but it survives a vendor directory somebody removed by hand.
if [[ -f "${SHOPWARE_STAMP}" ]] \
  && [[ "$(cat "${SHOPWARE_STAMP}")" == "${SHOPWARE_TARGET}" ]] \
  && [[ -f "${SHOPWARE_DIR}/vendor/autoload.php" ]]; then
  echo "==> Reusing Shopware ${SHOPWARE_TARGET}"
  exit 0
fi

echo "==> Installing Shopware ${SHOPWARE_TARGET} for analysis and tests only"
rm -rf "${SHOPWARE_DIR}"
mkdir -p "${SHOPWARE_DIR}"
# --no-plugins: this install only ever supplies classes to read. Nothing needs a
# Composer plugin's code to run, and without it symfony/runtime's plugin has no
# allow-plugins config to be trusted by and aborts the install.
composer require "shopware/core:${SHOPWARE_CONSTRAINT}" \
  --working-dir="${SHOPWARE_DIR}" \
  --ignore-platform-req=ext-* \
  --no-interaction \
  --no-progress \
  --no-plugins
printf '%s' "${SHOPWARE_TARGET}" > "${SHOPWARE_STAMP}"
