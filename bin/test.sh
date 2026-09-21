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

    # The dump-autoload below bakes its up-level hops for vendor/'s
    # single-segment depth. Moving that same directory back to
    # .tmp/dev-vendor, two segments down, would leave it wrong again for the
    # next run (and for Task 2's PHPStan, which reads it directly) unless it
    # is re-dumped for that depth here.
    COMPOSER_VENDOR_DIR="${DEV_VENDOR_DIR}" composer dump-autoload --no-interaction
  fi

  mv "${BACKUP_DIR}" "${VENDOR_DIR}"
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
