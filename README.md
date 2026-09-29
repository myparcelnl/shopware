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

Run this in the web container, on the PHP version the shop and CI use; host PHP
resolves a different install.

Run it after a fresh clone and after every dependency change. It installs the
dependencies, runs php-scoper, replaces `vendor/` with the scoped copy, dumps the
autoloader and asserts the result. The plug-in's own `src/` is deliberately left
unscoped: it talks to the real Shopware classes.

`make scope`, from the root of the docker environment, does the same and clears
Shopware's cache afterwards. Every `make` target runs on the host and starts its
own work in the container.

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
that failure, and the second is the answer to it. Both of these, and the
`composer update` above, run in the web container.

After that, one command keeps the two in step:

```shell
composer watch
```

Run this one on the host, not in the container: bind mounts do not forward file
events into the container, so a watcher inside it would have to poll anyway. It
starts the scoping in the container itself.

It polls the PDK's `src/` and `config/` and the plug-in's `src/` and `config/`. A
change in the PDK re-scopes only `vendor/myparcelnl/pdk` and clears Shopware's
cache, which takes about ten seconds. The PDK's `config/` counts because the PDK
builds its service container from `config/pdk-*.php`, and the whole checkout is
scoped, not only its PHP files. A change to either `composer.json` runs a full
scope instead. A change in the plug-in's `src/` or `config/` only clears the cache,
because neither is scoped.

Do not run `composer test` while the watch runs: the tests put the development
install in `vendor/` for the length of a run, and a scope landing in the middle of
that would write to the wrong tree.

Remove the path repository and run `composer update myparcelnl/pdk` again before
you commit, so the branch carries the published version:

```shell
composer update myparcelnl/pdk
composer scope
```

Both of these run in the web container. `composer update` alone leaves `vendor/`
unscoped and mixed with the development dependencies, and the shop will not boot
until `composer scope` puts a scoped, production-only `vendor/` back.

### Tests

```shell
composer test
```

Run this in the web container as well; CI runs the same command in a container on
the same PHP version.

For the length of the run, `vendor/` and a second, unscoped install in
`.tmp/dev-vendor` swap places, so Pest runs against the development dependencies
while the shop, which needs the scoped `vendor/`, does not boot. Without a scoped
`vendor/` there is nothing to swap, so the command refuses to start and names
`composer scope`. Once it starts, the swap reverses itself when the run ends,
including on failure. `.tmp/dev-vendor` is built the first time and reused after
that, unless `composer.json` or `composer.lock` changed.

Do not run `composer test` and `composer watch` at the same time: a scope landing
mid-swap would write to the wrong tree.

Arguments pass straight through, e.g. `composer test -- --filter=logger`.

### Static analysis

```shell
composer analyse
```

Run this in the web container too, and run `composer scope` there first.

PHPStan reads three worlds at once: the plug-in's own `src/`, Shopware's Symfony 7
runtime and the `_MyParcel`-prefixed copies in the scoped `vendor/`. Without a
scoped `vendor/`, PHPStan reports every `_MyParcel` class as missing instead of
analysing the plug-in.

Shopware and the PDK cannot live in one composer install: the PDK caps
`symfony/http-foundation` at 6, Shopware 6.7 needs 7.4. The first run installs
Shopware on its own in `.tmp/shopware` and PHPStan itself in `.tmp/tools`. Later
runs reuse both and finish in seconds. Each install carries a stamp of what it was
built for, so bumping the Shopware constraint in `bin/analyse.sh`, bumping
`phpstan/phpstan` in `require-dev` or switching PHP version rebuilds what that
change affects instead of reusing the old install.

The baseline in `phpstan-baseline.php` is empty on purpose. Fix what PHPStan
reports; do not add to it.

### Translations

The MyParcel texts come from the shared PDK translations sheet and live in
`config/pdk/translations`. To refresh them, run this in the web container:

```shell
corepack yarn install
corepack yarn translations:import
```

Commit the changed JSON files. The plug-in shows texts in the language of the
Shopware request and falls back to English.

`fast-glob` is in `devDependencies` only because `@myparcel-dev/pdk-app-builder`
imports it without declaring it. Remove it once the builder declares it itself.

## Connecting a MyParcel account

Until the admin settings screen exists, set the API key from the console. The
key is validated when it is saved:

```shell
bin/console myparcel:account:update '<api key>' --acceptance
bin/console myparcel:account:show
```

Run these in the web container, from the shop root.

Leave out `--acceptance` to use the production API. A Belgian account runs on
the SendMyParcel proposition automatically. `myparcel:account:show` never prints
the key.

Settings and the account are stored installation-wide in `system_config`, under
`MyParcelShopware.pdk.*`.

## Logging

The plug-in writes to its own Monolog channel, `myparcel`, which lands in
`var/log/myparcel_<env>-<date>.log`. Level `info` by default, `debug` in dev.
