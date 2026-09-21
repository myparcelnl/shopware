# MyParcel for Shopware

MyParcel plug-in for Shopware 6.7 and up, built on the
[MyParcel PDK](https://github.com/myparcelnl/pdk).

> **Status: under construction.** This plug-in is being built as part of epic
> [INT-1748](https://myparcelnl.atlassian.net/browse/INT-1748). It is not
> released and not ready for use in a shop.
>
> For Shopware 6.5 and 6.6, use [myparcelnl/shopware6](https://github.com/myparcelnl/shopware6),
> which stays available and receives security fixes.

## Requirements

|          |                           |
| -------- | ------------------------- |
| Shopware | 6.7.12 or newer           |
| PHP      | 8.2 – 8.5                 |
| Node     | 24 (only to build assets) |

## Development

Use [docker-shopware](https://github.com/myparcelnl/docker-shopware) for a running
Shopware 6.7 environment. Check this repository out at
`custom/plugins/MyParcelShopware` — the directory name is the plug-in's technical
name and Shopware installs it by that name.

### The vendor directory has to be scoped

The PDK requires `symfony/http-foundation` 6 or lower and `php-di` 6, while Shopware
6.7 runs on Symfony 7.4. php-scoper gives the bundled dependencies a `_MyParcel`
prefix, after which both versions live in the same process. Without this the
plug-in cannot boot.

```shell
composer scope
```

Run it after a fresh clone and after every dependency change. It installs the
dependencies, runs php-scoper, replaces `vendor/` with the scoped copy, dumps the
autoloader and asserts the result. The plug-in's own `src/` is deliberately left
unscoped: it talks to the real Shopware classes.

From the docker environment, `make scope` does the same and clears Shopware's cache
afterwards.

### Working on the PDK

To work on the PDK and the plug-in at the same time, point composer at your local
checkout and mount it in the container at the same path. `docker-compose.override.yml`
in the docker environment already has the mount; uncomment it and run `make up`.

Add the path repository to `composer.json` and install it:

```json
"repositories": [
  {
    "type": "path",
    "url": "/Users/<user>/Projects/pdk"
  }
]
```

```shell
composer update myparcelnl/pdk
```

Composer installs a path repository as a symlink, and `composer scope` cannot use
one: php-scoper's finder does not follow links, and the scoped copy it writes
replaces `vendor/` entirely. A full scope therefore leaves `vendor/myparcelnl/pdk`
missing. It says so and exits 3, with the rest of `vendor/` scoped and in place.
`bin/scope-pdk.sh` adds the PDK to it.

```shell
composer scope; bin/scope-pdk.sh
```

Use `;`, not `&&`: on a linked checkout the first command is supposed to end in
that failure, and the second is the answer to it.

After that, one command keeps the two in step:

```shell
composer watch
```

It polls your PDK checkout and the plug-in's `src/`. A change in the PDK re-scopes
only `vendor/myparcelnl/pdk` and clears Shopware's cache, which takes about ten
seconds. A change to either `composer.json` runs a full scope instead. A change in
`src/` only clears the cache, because that directory is not scoped.

Do not run `composer test` while the watch runs: the tests put the development
install in `vendor/` for the length of a run, and a scope landing in the middle of
that would write to the wrong tree.

Remove the path repository and run `composer update myparcelnl/pdk` again before
you commit, so the branch carries the published version.

## Logging

The plug-in writes to its own Monolog channel, `myparcel`, which lands in
`var/log/myparcel_<env>-<date>.log`. Level `info` by default, `debug` in dev.
