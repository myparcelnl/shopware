#!/usr/bin/env bash

# Runs the Pest suite against the unscoped development install.
#
# Pest resolves its own autoloader as dirname(__DIR__, 4) . '/vendor/autoload.php'
# and derives its root path from that, so the vendor directory has to be named
# vendor and sit one level below the plug-in root. That name belongs to the
# scoped vendor the shop needs, so the two change places for the length of a run
# and an EXIT trap puts them back. bin/scope.sh uses the same pattern.
#
# While the tests run the shop has an unscoped vendor and will not boot. A run
# takes seconds, and the swap is two moves on one filesystem.
#
# Arguments pass straight through, so `composer test -- --filter=logger` works.

set -Eeuo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="${PLUGIN_DIR}/.tmp"
DEV_VENDOR_DIR="${TMP_DIR}/dev-vendor"
BACKUP_DIR="${TMP_DIR}/vendor.scoped"
VENDOR_DIR="${PLUGIN_DIR}/vendor"

cd "${PLUGIN_DIR}"

bin/dev-vendor.sh

# Only a run that was killed outright leaves this behind. Restoring it
# automatically could overwrite a vendor directory somebody rebuilt since.
if [[ -d "${BACKUP_DIR}" ]]; then
  cat >&2 <<MESSAGE
A previous test run left ${BACKUP_DIR} behind, so vendor/ may be the development
install instead of the scoped one. Check which is which, put the scoped copy back
as vendor/, and remove the leftover directory.
MESSAGE
  exit 1
fi

# Stateless, like bin/scope.sh's restore_vendor: keyed only on whether the
# backup exists, not on a flag set before the move it would guard. If the mv
# below that creates BACKUP_DIR fails, this is a no-op and the scoped vendor/
# is left exactly as it was.
restore_vendor() {
  [[ -d "${BACKUP_DIR}" ]] || return 0

  if [[ -d "${VENDOR_DIR}" ]]; then
    mv "${VENDOR_DIR}" "${DEV_VENDOR_DIR}"
  fi

  # Restoring the scoped copy has to be unconditional, like bin/scope.sh's
  # restore: nothing after this point may be able to abort the function and
  # strand it in BACKUP_DIR. That is why the dev-vendor autoload refresh below
  # runs after this line, and with `|| true` — a subprocess (composer, PHP
  # itself) can fail for reasons that have nothing to do with the swap, and
  # under `set -e` that failure would otherwise cut the restore short.
  mv "${BACKUP_DIR}" "${VENDOR_DIR}"

  # Best-effort: refreshes .tmp/dev-vendor's autoloader for its own depth (see
  # the comment above the forward dump-autoload below). If this fails,
  # .tmp/dev-vendor is left with a stale autoloader until something forces a
  # fresh install (e.g. removing the directory) — recoverable, unlike a
  # missing vendor/, which is why this step may not gate the restore above.
  if [[ -d "${DEV_VENDOR_DIR}" ]]; then
    COMPOSER_VENDOR_DIR="${DEV_VENDOR_DIR}" composer dump-autoload --no-interaction || true
  fi
}

trap restore_vendor EXIT

if [[ -d "${VENDOR_DIR}" ]]; then
  mv "${VENDOR_DIR}" "${BACKUP_DIR}"
fi
mv "${DEV_VENDOR_DIR}" "${VENDOR_DIR}"

# Composer bakes the vendor directory's depth relative to the project root into
# the generated autoload files as a fixed number of dirname() calls. That depth
# was ".tmp/dev-vendor" (two levels) when bin/dev-vendor.sh installed it; now
# that it sits at "vendor" (one level), the same files resolve our own src/ and
# tests/ PSR-4 mappings one directory too high. Dumping again, from here, fixes
# it for the current depth without touching what is installed.
composer dump-autoload --no-interaction

vendor/bin/pest "$@"
