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

|          |                                                   |
| -------- | ------------------------------------------------- |
| Shopware | 6.7.12 or newer                                   |
| PHP      | 8.2 – 8.5                                         |
| Node.js  | 24, version 24.21 or later (only to build assets) |
| pnpm     | 12, version 12.11 or later (only to build assets) |

Do not install Volta: its `yarn` does not go through corepack, so the `packageManager` field in `package.json` does not stop it.

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

## Checkout

The delivery options show on the checkout confirm page, below the shipping
methods. The consumer gets them when:

- "Enable delivery options" in the checkout settings is on, or was never saved;
- the cart has deliverable products, and no product has the delivery options
  turned off;
- the merchant linked a package type to the chosen shipping method, in
  "Allowed shipping methods" of the checkout settings.

The plug-in creates no shipping methods.

Each fee of the chosen delivery options is a separate line in the cart, with the
tax rules of the shipping costs. The pickup discount never makes the shipping
costs negative, also not after a free-shipping promotion.

The "Custom CSS" of the checkout settings goes in a `<style>` element in the
wrapper of the delivery options.

The checkout script finds the line items, the totals, the order form, the
order hash and the order button by the class names and ids of the default
Storefront theme. A theme that changes them sets its own in Twig, without a new
build of the script. Give only the selectors that differ:

```twig
{% sw_extends '@MyParcelShopware/storefront/page/checkout/confirm/confirm-shipping.html.twig' %}

{% block myparcel_checkout_delivery_options %}
    {% set myparcelSelectors = {summary: '.my-theme-summary', submit: '#my-theme-order-button'} %}
    {{ parent() }}
{% endblock %}
```

The keys are `products`, `summary`, `form`, `hash` (inside the form) and
`submit`.

The plug-in stores the choice in `myparcel_order_data` with the order. The
shipping address of the order stays the consumer's address, also for a pickup
point.

## Tracking codes

When a shipment gets a barcode, the plug-in adds it to the tracking codes of the
order delivery. That is the delivery that the shipping address comes from.
Shopware shows these codes in the admin and in the customer account, with the
tracking URL of the shipping method. The plug-in removes the barcode of a deleted
shipment and keeps the codes that the merchant entered. A return shipment adds
no code.

The default mail templates of Shopware do not show tracking codes. To show them
in a mail, add this to the template:

```twig
{% for delivery in order.deliveries %}
    {% for trackingCode in delivery.trackingCodes %}
        {{ trackingCode }}
    {% endfor %}
{% endfor %}
```

## Routes

The plug-in sends several kinds of requests to the PDK:

| Route                          | Used by          | Access                                     |
| ------------------------------ | ---------------- | ------------------------------------------ |
| `/api/_action/myparcel/pdk`    | Admin app        | Admin API token with `myparcel:access`     |
| `/api/_action/myparcel/view`   | Admin module     | Admin API token with `myparcel:access`     |
| `/myparcel/pdk`                | Checkout         | Storefront, also via XMLHttpRequest        |
| `/myparcel/delivery-options`   | Checkout         | Storefront, XMLHttpRequest, POST only      |
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
