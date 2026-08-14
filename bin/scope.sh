#!/usr/bin/env bash

# Produces a scoped vendor directory: every third-party dependency gets the
# _MyParcelNL prefix so the PDK's Symfony 6 and php-di 6 can coexist with the
# Symfony 7 runtime Shopware 6.7 ships. Without this the plugin cannot boot.
#
# Run it from the plugin root, or via `composer scope`. The plugin's own src/
# is deliberately left alone — it talks to the real Shopware classes.

set -Eeuo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="${PLUGIN_DIR}/.tmp"
SCOPER_DIR="${TMP_DIR}/php-scoper"
SCOPER_STAMP="${SCOPER_DIR}/.requested-version"
SCOPED_DIR="${TMP_DIR}/scoped/vendor"
VENDOR_DIR="${PLUGIN_DIR}/vendor"
BACKUP_DIR="${TMP_DIR}/vendor.previous"
SCOPER_VERSION="${PHP_SCOPER_VERSION:-^0.18.0}"
PHP_VERSION="$(php -r 'echo PHP_VERSION;')"

cd "${PLUGIN_DIR}"

# Every step below can fail, and until the swap succeeds the plugin has no usable
# vendor directory. Keep the old one aside so a failure leaves a working plugin
# behind rather than an empty vendor and an error that explains nothing.
restore_vendor() {
  if [[ -d "${BACKUP_DIR}" ]]; then
    rm -rf "${VENDOR_DIR}"
    mv "${BACKUP_DIR}" "${VENDOR_DIR}"
    echo "Restored the previous vendor directory." >&2
  fi
}

# On EXIT rather than ERR: the verification steps below bail out with an explicit
# `exit 1`, which an ERR trap does not see, and a rejected vendor must not be left
# installed. Removing the backup on success turns this into a no-op.
trap restore_vendor EXIT

# Start from a clean unscoped install every time. Composer considers an already
# scoped vendor up to date ("Nothing to install"), which would feed php-scoper
# its own output. It happens to guard against re-prefixing, but relying on that
# makes the outcome depend on the state we started in.
echo "==> Installing dependencies"
mkdir -p "${TMP_DIR}"
rm -rf "${BACKUP_DIR}"
if [[ -d "${VENDOR_DIR}" ]]; then
  mv "${VENDOR_DIR}" "${BACKUP_DIR}"
fi
composer install --no-dev --no-interaction --no-progress

# php-scoper lives outside the plugin vendor on purpose: anything inside
# vendor/ ends up scoped, and a scoped scoper cannot scope.
#
# The cached copy is reused only when it was installed for this same requested
# version and it actually runs. The version stamp catches a bumped
# PHP_SCOPER_VERSION, which the cache would otherwise silently ignore; running it
# catches a copy installed under a different PHP, because Composer resolves
# php-scoper's dependencies against the PHP that installed it and the platform
# check then fails with an exit code and no output at all.
scoper_is_usable() {
  [[ -f "${SCOPER_STAMP}" ]] \
    && [[ "$(cat "${SCOPER_STAMP}")" == "${SCOPER_VERSION}" ]] \
    && php "${SCOPER_DIR}/vendor/bin/php-scoper" --version >/dev/null 2>&1
}

if scoper_is_usable; then
  echo "==> Reusing php-scoper ${SCOPER_VERSION} from ${SCOPER_DIR#"${PLUGIN_DIR}/"} (PHP ${PHP_VERSION})"
else
  echo "==> Installing php-scoper ${SCOPER_VERSION} for PHP ${PHP_VERSION}"
  rm -rf "${SCOPER_DIR}"
  mkdir -p "${SCOPER_DIR}"
  composer require "humbug/php-scoper:${SCOPER_VERSION}" \
    --working-dir="${SCOPER_DIR}" \
    --ignore-platform-req=ext-* \
    --no-interaction \
    --no-progress

  if ! php "${SCOPER_DIR}/vendor/bin/php-scoper" --version >/dev/null 2>&1; then
    echo "FAILED: php-scoper ${SCOPER_VERSION} does not run on PHP ${PHP_VERSION}" >&2
    exit 1
  fi

  printf '%s' "${SCOPER_VERSION}" > "${SCOPER_STAMP}"
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
rm -rf "${VENDOR_DIR}"
mv "${SCOPED_DIR}" "${VENDOR_DIR}"

# The scoped packages declare prefixed namespaces, so the autoload maps that
# came out of the unscoped install no longer match. Optimized but not
# authoritative: a new class in src/ should still resolve via PSR-4 without
# having to re-scope.
echo "==> Dumping autoloader"
composer dump-autoload --no-dev --optimize --no-interaction

echo "==> Verifying"
if grep -rq '^namespace Symfony\\Component\\HttpFoundation;' "${VENDOR_DIR}"; then
  echo "FAILED: unprefixed Symfony\\Component\\HttpFoundation left in vendor" >&2
  exit 1
fi

if ! grep -rq '^namespace _MyParcelNL\\Symfony\\Component\\HttpFoundation;' "${VENDOR_DIR}"; then
  echo "FAILED: no prefixed Symfony\\Component\\HttpFoundation found in vendor" >&2
  exit 1
fi

# Asserts the nullable patcher from scoper.vendor.inc.php did its work, using that
# patcher's own pattern against whole files. A grep for one known signature would
# pass on a multiline declaration or on any other type, which is worth nothing as a
# guard: the point is to notice when the patcher stops matching, not to re-check the
# one case we happened to know about.
if ! SCOPED_VENDOR="${VENDOR_DIR}" php <<'PHP'
<?php
$directory = getenv('SCOPED_VENDOR') . '/php-di';

if (!is_dir($directory)) {
    fwrite(STDERR, "php-di is missing from the scoped vendor\n");
    exit(1);
}

$pattern  = '/([(,]\s*)([a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*)(\s+\$[a-zA-Z_][a-zA-Z0-9_]*\s*=\s*null\b)/';
$offences = [];
$files    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

foreach ($files as $file) {
    if (!$file->isFile() || 'php' !== $file->getExtension()) {
        continue;
    }

    if (preg_match($pattern, (string) file_get_contents($file->getPathname()), $matches)) {
        $offences[] = sprintf('%s: %s', $file->getFilename(), trim($matches[0]));
    }
}

foreach (array_slice($offences, 0, 5) as $offence) {
    fwrite(STDERR, "  {$offence}\n");
}

exit($offences ? 1 : 0);
PHP
then
  echo "FAILED: php-di still has implicitly nullable parameters (deprecated on PHP 8.4+)" >&2
  exit 1
fi

# Made it. Dropping the backup disarms the EXIT trap.
rm -rf "${BACKUP_DIR}"

echo "Done. vendor/ is scoped with the _MyParcelNL prefix."
