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

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the setup of a development environment,
the checks and where code goes.

## Connecting a MyParcel account

Open Settings → Extensions → MyParcel in the admin, or "Open extension" in the
context menu of the plug-in in the extension list, and enter the API key there. A user needs the
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

## Product settings

The MyParcel settings of a product are custom fields named `myparcel_*`, in the
custom field set `myparcel_product`. `ProductCustomFieldSet` creates the set on
install and brings it in line with the PDK's product settings on every plug-in
update. Uninstalling without "keep user data" removes the set and the values of
its fields from every product.

The set has no relation to the product entity, so the fields do not show in the
"Custom fields" card of the product page.
The "MyParcel" tab of the product page shows these settings, with its own
settings for each variant. The settings stay on the page while the merchant opens
another tab. The Shopware Save submits the changed settings to the PDK first, then
saves the product without the `myparcel_*` fields, so it cannot write old values
back.

## Routes

The plug-in sends several kinds of requests to the PDK:

| Route                          | Used by          | Access                                     |
| ------------------------------ | ---------------- | ------------------------------------------ |
| `/api/_action/myparcel/pdk`    | Admin app        | Admin API token with `myparcel:access`     |
| `/api/_action/myparcel/view`   | Admin module     | Admin API token with `myparcel:access`     |
| `/myparcel/pdk`                | Checkout         | Storefront, also via XMLHttpRequest        |
| `/api/myparcel/webhook/{hash}` | MyParcel webhook | Public; only the stored hash is accepted   |

The view route returns the PDK markup of one admin screen (`?view=pluginSettings`)
as JSON, with the URLs of the PDK admin app. The Shopware admin is a single-page
app, so no PHP page holds that markup. Some screens need a context: `orderIds[]`
for the order list, `orderId` for the order detail and `productId` for the product
detail.

A webhook with a missing or wrong hash gets `404`. Until the webhooks are
registered at MyParcel, no hash is stored, and every webhook gets `404`.

`src/Pdk/Http/PdkHttpBridge.php` is the only class that converts between
Shopware's Symfony classes and the `_MyParcel`-scoped copies in the PDK.

## Logging

The plug-in writes to its own Monolog channel, `myparcel`, which lands in
`var/log/myparcel_<env>-<date>.log`. Level `info` by default, `debug` in dev.
