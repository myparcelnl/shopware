#!/usr/bin/env bash

# Re-scopes just the linked PDK into the existing scoped vendor.
#
# bin/scope.sh rebuilds everything, which is right after a dependency change and
# far too slow for every save. Nothing else moves while you edit the PDK, so
# only vendor/myparcelnl/pdk has to be rewritten.

set -Eeuo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="${PLUGIN_DIR}/.tmp"
SCOPER_DIR="${TMP_DIR}/php-scoper"
OUTPUT_DIR="${TMP_DIR}/scoped-pdk"
BACKUP_DIR="${TMP_DIR}/pdk.previous"
TARGET_DIR="${PLUGIN_DIR}/vendor/myparcelnl/pdk"

cd "${PLUGIN_DIR}"

if [[ ! -x "${SCOPER_DIR}/vendor/bin/php-scoper" ]]; then
  echo "php-scoper is not installed. Run 'composer scope' once first." >&2
  exit 1
fi

PDK_DIR="$(php -r '
  $json = json_decode(file_get_contents("composer.json"), true);
  foreach ($json["repositories"] ?? [] as $repository) {
      if (($repository["type"] ?? "") === "path" && str_contains($repository["url"] ?? "", "pdk")) {
          echo $repository["url"];
          return;
      }
  }
')"

if [[ -z "${PDK_DIR}" ]]; then
  echo "No linked PDK found in composer.json." >&2
  exit 1
fi

if [[ ! -d "${PDK_DIR}" ]]; then
  echo "The linked PDK path ${PDK_DIR} does not exist here." >&2
  exit 1
fi

rm -rf "${OUTPUT_DIR}"

# A config of its own: the finder points at the linked source instead of vendor,
# and everything else matches scoper.vendor.inc.php so the result is identical
# to what a full scope would produce.
#
# scoper.vendor.inc.php builds its own finder on `vendor`, which Symfony's
# Finder validates the moment the file is required, so this has to run from the
# plug-in root. The `cd` above is what guarantees that.
cat > "${TMP_DIR}/scoper.pdk.inc.php" <<PHP
<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

\$config = require '${PLUGIN_DIR}/scoper.vendor.inc.php';

\$config['finders'] = [
    Finder::create()
        ->files()
        ->ignoreVCS(true)
        ->notName('/LICENSE|.*\\\\.md|.*\\\\.dist|Makefile|composer\\\\.lock/')
        ->exclude(['tests', 'node_modules', 'vendor', '.cache'])
        ->in('${PDK_DIR}'),
];

unset(\$config['exclude-files']);

return \$config;
PHP

php -d memory_limit=-1 "${SCOPER_DIR}/vendor/bin/php-scoper" add-prefix \
  --config="${TMP_DIR}/scoper.pdk.inc.php" \
  --output-dir="${OUTPUT_DIR}" \
  --force \
  --no-ansi \
  --no-interaction

# php-scoper exits 0 on a config whose finder found the right files and whose
# prefixing did nothing, and after the swap the shop runs whatever came out. So
# assert here, before the window, where a failure costs nothing: the old tree is
# untouched and OUTPUT_DIR is thrown away by the next run.
#
# Two-sided, like the assertion in bin/scope.sh: an unprefixed http-foundation
# inside the PDK would put two Symfony versions in one process, which fails far
# away from here.
if [[ ! -d "${OUTPUT_DIR}/src" ]] \
  || grep -rq '^use Symfony\\Component\\HttpFoundation\\' "${OUTPUT_DIR}/src" \
  || ! grep -rq '^use _MyParcel\\Symfony\\Component\\HttpFoundation\\' "${OUTPUT_DIR}/src"; then
  echo "FAILED: the scoped PDK in ${OUTPUT_DIR} is missing or not correctly prefixed" >&2
  exit 1
fi

# The swap. Between moving the old tree aside and moving the new one in, the
# plug-in has no PDK at all and cannot boot, so that window holds nothing that
# can fail: no test, no command, no message. Everything that can fail runs
# before it, into OUTPUT_DIR, or after it, once the new tree is already in
# place.
#
# The trap covers the window itself — a signal, a full disk, a killed shell.
# It is stateless and keyed only on the backup existing, like restore_vendor in
# bin/scope.sh and bin/test.sh, so it is a no-op before the first mv and after
# the backup is dropped.
restore_pdk() {
  # -e or -L, not -d: what was moved aside may be the path repository's symlink,
  # and -e alone reports false for one whose target is gone.
  [[ -e "${BACKUP_DIR}" ]] || [[ -L "${BACKUP_DIR}" ]] || return 0

  rm -rf "${TARGET_DIR}" || true

  # mv into an existing directory nests the backup inside it instead of
  # replacing it, which hides the PDK rather than restoring it.
  if [[ -e "${TARGET_DIR}" ]] || [[ -L "${TARGET_DIR}" ]]; then
    echo "FATAL: could not clear ${TARGET_DIR}. The previous PDK is in ${BACKUP_DIR}; move it back by hand." >&2
    return 0
  fi

  mv "${BACKUP_DIR}" "${TARGET_DIR}"
  echo "Restored the previous scoped PDK." >&2
}

# Both of these can fail, so they go before the window, not inside it. A stale
# backup would make the trap restore the wrong tree, and a missing parent would
# make the second mv fail with the PDK already moved aside.
rm -rf "${BACKUP_DIR}"
mkdir -p "$(dirname "${TARGET_DIR}")"
trap restore_pdk EXIT

# -e or -L, not -d: a composer path repository installs the PDK as a symlink,
# and a symlink has to be moved aside too.
if [[ -e "${TARGET_DIR}" ]] || [[ -L "${TARGET_DIR}" ]]; then
  mv "${TARGET_DIR}" "${BACKUP_DIR}"
fi
# Replace rather than merge: a file deleted in the PDK has to disappear here too.
mv "${OUTPUT_DIR}" "${TARGET_DIR}"

# The new tree is in place and correct. Dropping the backup disarms the trap,
# so nothing below can undo it.
rm -rf "${BACKUP_DIR}"

# Everything from here is best-effort: it improves on a state that already
# works, and rolling back over it would throw away a good scope. The watch
# reads this script's exit code as "the PDK swap failed", so it must not report
# one of these.

# The PDK compiles its container into .cache; a stale copy hides the change that
# was just scoped.
rm -rf "${TARGET_DIR}/.cache" \
  || echo "warning: could not clear ${TARGET_DIR}/.cache. Remove it by hand if the change does not show up." >&2

# The classmap still points at the old file list. It is optimized but not
# authoritative, so PSR-4 resolves a class it is missing and a failure here
# costs speed, not correctness.
composer dump-autoload --no-dev --optimize --no-interaction \
  || echo "warning: could not dump the autoloader. It stays stale until the next scope." >&2

echo "Done. The linked PDK is scoped into vendor/myparcelnl/pdk."
