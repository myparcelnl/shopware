# Contributing

This document tells you how to get the plug-in running and how to add code to
it. The scripts in `bin/` explain in their headers how they work.

Unless a step says otherwise, run each command in the web container, from the
plug-in root. The container has the PHP version that the shop and CI use.

## Setup

Use [docker-shopware](https://github.com/myparcelnl/docker-shopware) for a running
Shopware 6.7 environment. Check this repository out at
`custom/plugins/MyParcelShopware`. Shopware installs the plug-in by that
directory name.

1. Scope the dependencies:

   ```shell
   composer scope
   ```

   The PDK needs Symfony 6 and Shopware 6.7 runs on Symfony 7.4, so the plug-in
   cannot boot from an unscoped `vendor/`. Run this again after each dependency
   change. `make scope`, from the root of the docker environment, does the same
   and clears the Shopware cache.

2. Import the translations:

   ```shell
   pnpm install
   pnpm translations:import
   ```

   The translations are not in git. Without them, `composer test` fails.

3. Build the PDK admin app:

   ```shell
   pnpm build:pdk
   ```

4. Build the Shopware admin module, from the shop root. Do not use
   `bin/build-administration.sh`: in this container it runs `npm ci` in the
   plug-in, which uses pnpm, and fails.

   ```shell
   bin/console bundle:dump && bin/console feature:dump
   cd vendor/shopware/administration/Resources/app/administration
   npm install --prefer-offline --omit=dev
   PROJECT_ROOT=/var/www/html SHOPWARE_ADMIN_BUILD_ONLY_EXTENSIONS=1 npm run build
   cd /var/www/html && bin/console assets:install
   ```

After each build of a bundle, run `bin/console assets:install` from the shop root.
The shop serves the copy in `public/bundles`, not the build output.

## Checks

```shell
composer test
composer analyse
pnpm test:pdk
```

- `composer test` swaps `vendor/` with a development install while it runs. The
  shop does not boot during the run. Arguments go to Pest, for example
  `composer test -- --filter=logger`.
- Do not run `composer test` while `composer watch` runs.
- The PHPStan baseline in `phpstan-baseline.php` is empty. Fix what PHPStan
  reports, and do not add to the baseline.
- `pnpm test:pdk:coverage` runs the JS tests with coverage, as CI does.

## Working on the PDK

To use a local PDK checkout:

1. Uncomment the mount in `docker-compose.override.yml` of the docker environment,
   and run `make up`.
2. Add the path repository to `composer.json` and install it:

   ```json
   "repositories": [{"type": "path", "url": "/Users/<user>/Projects/pdk"}]
   ```

   ```shell
   composer update myparcelnl/pdk
   composer scope; bin/scope-pdk.sh
   ```

   Use `;`, not `&&`. On a linked PDK, `composer scope` stops with exit code 3,
   and `bin/scope-pdk.sh` adds the PDK.

3. Run `composer watch` **on the host**. It scopes the PDK again after each change.

Before you commit, remove the path repository and run `composer update myparcelnl/pdk`
and `composer scope` again.

To use a local js-pdk checkout, mount it in the web container on its host path,
and add `link:` overrides to `pnpm-workspace.yaml`. Do not commit them.

```yaml
overrides:
  '@myparcel-dev/pdk-admin': 'link:/Users/<user>/Projects/js-pdk/apps/admin'
  '@myparcel-dev/pdk-admin-preset-default': 'link:/Users/<user>/Projects/js-pdk/apps/admin-preset-default'
```

## Admin code

The admin has two bundles:

| Bundle                | Source                             | Vue                                 |
| --------------------- | ---------------------------------- | ----------------------------------- |
| Shopware admin module | `src/Resources/app/administration` | The Vue of the Shopware admin       |
| PDK admin app         | `src/Resources/app/pdk`            | Its own Vue, in a separate IIFE     |

Put a component in the place for its kind:

| Kind                                      | Location                                                     | Name                         |
| ----------------------------------------- | ------------------------------------------------------------ | ---------------------------- |
| Change to a Shopware component            | `administration/src/module/<module>/extension/<sw-name>/`    | The Shopware name            |
| New component in the Shopware admin       | `administration/src/module/<module>/{component,view,page}/<name>/` | `myparcel-<name>` |
| Replacement of a PDK component            | `pdk/src/components/`                                        | `Shopware<Name>.vue`         |

- A change to a Shopware component uses `Shopware.Component.override`. A new
  component uses `Shopware.Component.register`. The module `index.js` registers
  them.
- A PDK replacement goes into `components` in `pdk/src/config.ts`, on its
  `AdminComponent` key.
- Components in the Shopware admin module use the Options API, as Shopware does.
- Components in the PDK admin app follow the
  [Vue style guide](https://vuejs.org/style-guide/) and use the Composition API
  with `<script setup>`, as the PDK does.

## Translations

All texts come from the PDK translations sheet. Texts for Shopware are in the tab
`app:shopware`. To add a text to the admin module:

1. Add a key that starts with `shopware_admin_` to the tab `app:shopware`.
2. Run `pnpm translations:import`.
3. Use it as `$tc('myparcel.<key>')`.

`fast-glob` is in `devDependencies` only because `@myparcel-dev/pdk-app-builder`
uses it and does not declare it. Remove it when the builder declares it.

## Migrations

| Directory           | Runner                                   | Use it for                                                                  |
| ------------------- | ---------------------------------------- | --------------------------------------------------------------------------- |
| `src/Migration`     | Shopware, on plug-in install and update  | The plug-in's own tables: `myparcel_order_data`, `myparcel_order_shipment`  |
| `src/Pdk/Migration` | The PDK installer (`migrationDirectory`) | Changes to the PDK's own data, such as stored settings                      |

Each runner keeps its own list of migrations, so never put a migration in both
directories. A Shopware migration is a `MigrationStep` class named
`Migration<timestamp><Name>`. A PDK migration is a file named
`YYYY_MM_DD_HHMMSS_<name>.php`. Do not add a PDK migration before
[INT-2154](https://myparcelnl.atlassian.net/browse/INT-2154) is done: the plug-in
does not run the PDK installer yet.

## Code conventions

- Give every public method a docblock that says what it does and when to use it.
  Add `@param` and `@return` only when the signature does not show the type.
  - A constructor that only takes dependencies needs none.
  - A method that only fulfils an interface without logic of its own, such as the
    placeholders in `src/Pdk/Placeholder/`, needs none.
- Mark follow-up work with `@TODO INT-<number>: <what to do>`, with the key of a
  real ticket.
