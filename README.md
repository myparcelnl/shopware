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

The MyParcel texts come from the shared PDK translations sheet. They are built
into `config/pdk/translations` and not versioned, the same as in the WooCommerce
and PrestaShop plug-ins: CI builds them before the tests. To build them locally,
run this in the web container:

```shell
corepack pnpm install
corepack pnpm translations:import
```

Without these files every PDK translation lookup throws "File does not exist",
and `composer test` fails. The plug-in shows texts in the language of the Shopware
request and falls back to English.

`fast-glob` is in `devDependencies` only because `@myparcel-dev/pdk-app-builder`
imports it without declaring it. Remove it once the builder declares it itself.

### Admin assets

The plug-in ships two built admin bundles. Both are in git, so a shop never
builds anything:

| Bundle                | Source                             | Output                                |
| --------------------- | ---------------------------------- | ------------------------------------- |
| Shopware admin module | `src/Resources/app/administration` | `src/Resources/public/administration` |
| PDK admin app         | `src/Resources/app/pdk`            | `src/Resources/public/pdk`            |

The PDK admin app is a separate IIFE with its own Vue, because the Shopware build
replaces `vue` with the Shopware Vue. The admin module loads it on the settings
page. Rebuild a bundle after you change its source, and commit the output with
the change.

Build the PDK admin app, and run the tests of both bundles, in the web container:

```shell
corepack pnpm build:pdk
corepack pnpm test:pdk
```

Build the Shopware admin module in the web container, from the shop root.
`bin/build-administration.sh` cannot do it: it runs `npm ci` in every plug-in
root with a `package.json`, and this plug-in uses pnpm.

```shell
bin/console bundle:dump && bin/console feature:dump
cd vendor/shopware/administration/Resources/app/administration
npm install --prefer-offline --omit=dev
PROJECT_ROOT=/var/www/html SHOPWARE_ADMIN_BUILD_ONLY_EXTENSIONS=1 npm run build
cd /var/www/html && bin/console assets:install
```

To work on js-pdk at the same time, mount your js-pdk checkout in the web
container on its host path (see `docker-compose.override.yml` in the docker
environment), and add `link:` overrides with absolute paths to
`pnpm-workspace.yaml`. Do not commit them.

```yaml
overrides:
  '@myparcel-dev/pdk-admin': 'link:/Users/<user>/Projects/js-pdk/apps/admin'
  '@myparcel-dev/pdk-admin-preset-default': 'link:/Users/<user>/Projects/js-pdk/apps/admin-preset-default'
```

## Connecting a MyParcel account

Open Settings → Extensions → MyParcel in the admin, or the "Configure" button of
the plug-in in the extension list, and enter the API key there. A user needs the
"Use MyParcel" permission (Settings → Users & permissions → Roles → Detailed
privileges); an admin user has it.

The key can also be set from the console. It is validated when it is saved:

```shell
read -rs MYPARCEL_API_KEY && export MYPARCEL_API_KEY
docker compose exec -e MYPARCEL_API_KEY web bin/console myparcel:account:update --acceptance
unset MYPARCEL_API_KEY
docker compose exec web bin/console myparcel:account:show
```

These commands run on the host, from the docker-shopware root. `-e MYPARCEL_API_KEY`
without `=value` copies the variable from your shell, so the key is not on a
command line, in `ps` or in the shell history.

Leave out `--acceptance` to use the production API. A Belgian account runs on
the SendMyParcel proposition automatically. `myparcel:account:show` never prints
the key.

Settings and the account are stored installation-wide in `system_config`, under
`MyParcelShopware.pdk.*`.

## Routes

The plug-in sends four kinds of requests to the PDK:

| Route                          | Used by          | Access                                     |
| ------------------------------ | ---------------- | ------------------------------------------ |
| `/api/_action/myparcel/pdk`    | Admin app        | Admin API token with `myparcel:access`     |
| `/api/_action/myparcel/view`   | Admin module     | Admin API token with `myparcel:access`     |
| `/myparcel/pdk`                | Checkout         | Storefront, also via XMLHttpRequest        |
| `/api/myparcel/webhook/{hash}` | MyParcel webhook | Public; only the stored hash is accepted   |

The view route returns the PDK markup of one admin screen (`?view=pluginSettings`)
as JSON, with the URLs of the PDK admin app. The Shopware admin is a single-page
app, so no PHP page holds that markup.

A webhook with a missing or wrong hash gets `404`. Until the webhooks are
registered at MyParcel, no hash is stored, and every webhook gets `404`.

`src/Pdk/Http/PdkHttpBridge.php` is the only class that converts between
Shopware's Symfony classes and the `_MyParcel`-scoped copies in the PDK.

## Logging

The plug-in writes to its own Monolog channel, `myparcel`, which lands in
`var/log/myparcel_<env>-<date>.log`. Level `info` by default, `debug` in dev.
