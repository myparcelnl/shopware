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
# PHP_ARGS goes to the PHP binary, e.g. PHP_ARGS=-dpcov.enabled=1 in CI, where
# the image has pcov installed but disabled.

set -Eeuo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="${PLUGIN_DIR}/.tmp"
DEV_VENDOR_DIR="${TMP_DIR}/dev-vendor"
BACKUP_DIR="${TMP_DIR}/vendor.scoped"
VENDOR_DIR="${PLUGIN_DIR}/vendor"

cd "${PLUGIN_DIR}"

# The swap below only reverses itself because there is something to move aside:
# restore_vendor is keyed on the backup existing. Without a vendor/ no backup is
# made, the development install is moved in under that name and stays there, and
# .tmp/dev-vendor is gone as well — an unscoped install left where the shop
# expects the scoped one. Refuse before anything moves, with the same remedy
# bin/analyse.sh names.
if [[ ! -f vendor/autoload.php ]]; then
  echo "vendor/ is missing. Run 'composer scope' first." >&2
  exit 1
fi

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
# backup exists, not on a flag set before the move it would guard. If nothing
# below has run yet, this is a no-op and the scoped vendor/ is left exactly as
# it was.
#
# The restore itself is the pair `rm -rf` then `mv` below: nothing that can
# fail may sit between them, and everything before or after is best-effort.
# That is the one guarantee bin/scope.sh's restore_vendor has and the earlier
# version of this function did not — a failure anywhere else must not be able
# to leave vendor/ holding the wrong install or nothing at all.
restore_vendor() {
  [[ -d "${BACKUP_DIR}" ]] || return 0

  # Keeping the tested install saves the next run an install, but it is only
  # worth doing if it cannot get in the way: dev-vendor.sh rebuilds it from
  # scratch. Everything up to the mv below is therefore best-effort.
  if [[ -d "${VENDOR_DIR}" ]] && [[ ! -d "${DEV_VENDOR_DIR}" ]]; then
    mv "${VENDOR_DIR}" "${DEV_VENDOR_DIR}" || true
  fi

  rm -rf "${VENDOR_DIR}" || true

  # mv into an existing directory would nest the backup inside it instead of
  # replacing it, which hides the scoped vendor rather than restoring it.
  if [[ -d "${VENDOR_DIR}" ]]; then
    echo "FATAL: could not clear ${VENDOR_DIR}. The scoped vendor is in ${BACKUP_DIR}; move it back by hand." >&2
    return 0
  fi

  mv "${BACKUP_DIR}" "${VENDOR_DIR}"

  if [[ -d "${DEV_VENDOR_DIR}" ]]; then
    COMPOSER_VENDOR_DIR="${DEV_VENDOR_DIR}" composer dump-autoload --no-interaction \
      || echo "warning: could not refresh the autoloader in ${DEV_VENDOR_DIR}. It stays stale until the next test run." >&2
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

# PHP_ARGS is unquoted on purpose: it can hold more than one argument.
# shellcheck disable=SC2086
php ${PHP_ARGS:-} vendor/bin/pest "$@"
