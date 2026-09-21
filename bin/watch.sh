#!/usr/bin/env bash

# Keeps the scoped vendor in step with a locally linked PDK.
#
# bin/scope.sh replaces the whole vendor directory, which throws away the
# symlink a composer path repository creates. Without this watch you would have
# to re-scope by hand after every PDK change, and sooner or later look at stale
# code without noticing.
#
# The loop runs on the host: bind mounts do not forward inotify events into the
# container, so a watcher inside it would have to poll anyway. The scoping
# itself does run in the container, on the same PHP version as the shop.
#
# Do not run `composer test` while this is watching: it swaps vendor/ for the
# development install for the length of a run, and a scope landing in the middle
# of that would overwrite the wrong tree.

set -Eeuo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SHOPWARE_DIR="$(cd "${PLUGIN_DIR}/../../.." && pwd)"
TMP_DIR="${PLUGIN_DIR}/.tmp"
STAMP="${TMP_DIR}/watch.stamp"
INTERVAL=1
# Wait for an editor to finish writing a batch of files, so one save of ten
# files is one round instead of ten.
DEBOUNCE=1

cd "${PLUGIN_DIR}"

# The PDK location comes from composer, not from a hardcoded path, so the watch
# follows whatever the developer linked.
PDK_DIR="$(php -r '
  $json = json_decode(file_get_contents("composer.json"), true);
  foreach ($json["repositories"] ?? [] as $repository) {
      if (($repository["type"] ?? "") === "path" && str_contains($repository["url"] ?? "", "pdk")) {
          echo $repository["url"];
          return;
      }
  }
')"

if [[ -z "${PDK_DIR}" ]] || [[ ! -d "${PDK_DIR}" ]]; then
  cat >&2 <<'MESSAGE'
No linked PDK found.

Add a path repository to composer.json and install it, then start the watch again.
See "Working on the PDK" in the README.
MESSAGE
  exit 1
fi

in_container() {
  docker compose --project-directory "${SHOPWARE_DIR}" exec -T web bash -lc "$1"
}

changed_since_stamp() {
  # $1 is the directory, $2 the name pattern.
  #
  # Directories count as well as files. Deleting a file leaves nothing newer than
  # the stamp behind — it bumps the mtime of the directory that held it and
  # nothing else — so a probe on files alone would never notice a removed class,
  # and the scoped tree would go on serving it. The cost is that any other churn
  # in a directory fires a round too, an editor writing and removing a temporary
  # file for instance. That spurious round is accepted on purpose: it re-scopes
  # code that is already current, which is cheap, while a missed deletion shows
  # the developer a class that no longer exists.
  #
  # find exits non-zero on an unreadable or vanished path. That must not end the
  # watch, so the failure reads as "nothing changed" and the next round tries
  # again.
  find "$1" \( \( -type f -name "$2" \) -o -type d \) -newer "${STAMP}" \
    -print -quit 2>/dev/null || true
}

manifest_changed_since_stamp() {
  # Only the manifest at the root of $1 counts. Searching the whole tree would
  # match the composer.json of every installed package, and a full scope
  # rewrites all of those — which would make the watch re-trigger itself for
  # ever.
  find "$1" -maxdepth 1 -type f -name 'composer.json' -newer "${STAMP}" -print -quit 2>/dev/null || true
}

clear_cache() {
  in_container 'bin/console cache:clear --no-warmup'
}

# The scope functions scope and nothing else. Clearing the cache is a separate
# step below, because the two fail for different reasons and a reader has to be
# able to tell which one went wrong: a failed scope means the shop is running the
# previous code, a failed cache clear means it is running the previous code for
# one more command.
#
# They are also called from the left-hand side of a `||`, and bash switches -e
# off for the whole body of a function called that way, so every command that can
# fail carries its own check.

full_scope() {
  echo "==> Dependencies changed, running a full scope"
  # bin/scope-pdk.sh is not an optimisation here, it is part of the full scope.
  # A composer path repository installs the PDK as a symlink, php-scoper's finder
  # does not follow links, and bin/scope.sh replaces vendor/ with the finder's
  # output — so a full scope on a linked checkout leaves vendor/myparcelnl/pdk
  # missing altogether. bin/scope.sh says so and exits 3; that is the expected
  # outcome here and the step below is the answer to it. Any other non-zero
  # status is a real failure and bin/scope.sh has already put the previous vendor
  # back.
  #
  # This function reports its own failures rather than leaving it to the caller,
  # because what is left on disk depends on how far it got and only it knows.
  local status=0
  in_container "cd custom/plugins/MyParcelShopware && composer scope" || status=$?

  if [[ "${status}" -ne 0 ]] && [[ "${status}" -ne 3 ]]; then
    echo "Scoping failed. The previous vendor is still in place." >&2
    return 1
  fi

  scope_pdk && return 0

  # Exit 3 means bin/scope.sh dropped its backup on purpose, so that vendor is
  # gone: vendor/ is the tree it just built, and that tree has no PDK in it. The
  # plug-in cannot boot at all until the PDK lands, and no further save will
  # retry this on its own.
  if [[ "${status}" -eq 3 ]]; then
    echo "vendor/ has no PDK. Run bin/scope-pdk.sh in the web container; the shop will not boot until it succeeds." >&2
  else
    echo "Scoping the PDK failed. The previous PDK is still in place." >&2
  fi

  return 1
}

scope_pdk() {
  echo "==> Scoping the linked PDK"
  in_container "cd custom/plugins/MyParcelShopware && bin/scope-pdk.sh"
}

probe() {
  pdk_php="$(changed_since_stamp "${PDK_DIR}/src" '*.php')"
  pdk_json="$(manifest_changed_since_stamp "${PDK_DIR}")"
  own_json="$(manifest_changed_since_stamp "${PLUGIN_DIR}")"
  own_src="$(changed_since_stamp "${PLUGIN_DIR}/src" '*')"
}

mkdir -p "${TMP_DIR}"
touch "${STAMP}"

echo "Watching ${PDK_DIR} and src/. Press Ctrl-C to stop."

while true; do
  sleep "${INTERVAL}"

  probe

  if [[ -z "${pdk_php}${pdk_json}${own_json}${own_src}" ]]; then
    continue
  fi

  # Let the editor finish the batch, then ask again. A write that lands during
  # the debounce is not in the answers above, and stamping past it would hide
  # that change for good — a composer.json saved in this second would otherwise
  # take the PDK branch and never be seen again.
  sleep "${DEBOUNCE}"

  # The new stamp is taken before the probe and put in place after it, so a write
  # that lands while the probe runs is still newer than the stamp and the next
  # round picks it up. Stamping after the probe would drop that window. A watch
  # killed between the two leaves ${STAMP}.next behind; nothing reads it and the
  # next round overwrites it.
  touch "${STAMP}.next"
  probe
  mv "${STAMP}.next" "${STAMP}"

  # Failure must not end the watch. full_scope says for itself what it left
  # behind; the other two have one outcome each.
  scoped=1
  if [[ -n "${pdk_json}" ]] || [[ -n "${own_json}" ]]; then
    full_scope || scoped=0
  elif [[ -n "${pdk_php}" ]]; then
    scope_pdk || { scoped=0; echo "Scoping failed. The previous PDK is still in place." >&2; }
  else
    echo "==> Plug-in source changed"
  fi

  # Only worth doing when the step above got somewhere. After a failure the cache
  # is either still right, because nothing in vendor/ changed, or the plug-in has
  # no PDK and no cache clear is going to make it boot.
  if [[ "${scoped}" -eq 1 ]]; then
    clear_cache \
      || echo "The scoped vendor is current, but the cache clear failed. Run 'bin/console cache:clear' in the web container." >&2
  fi

  echo "==> Ready"
done
