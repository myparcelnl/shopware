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

| | |
|---|---|
| Shopware | 6.7.12 or newer |
| PHP | 8.2 – 8.5 |
| Node | 24 (only to build assets) |

## Development

Use [docker-shopware](https://github.com/myparcelnl/docker-shopware) for a running
Shopware 6.7 environment. Check this repository out at
`custom/plugins/MyParcelNLShopware` — the directory name is the plug-in's technical
name and Shopware installs it by that name.

### The vendor directory has to be scoped

The PDK requires `symfony/http-foundation` 6 or lower and `php-di` 6, while Shopware
6.7 runs on Symfony 7.4. php-scoper gives the bundled dependencies a `_MyParcelNL`
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

## Logging

The plug-in writes to its own Monolog channel, `myparcel`, which lands in
`var/log/myparcel_<env>-<date>.log`. Level `info` by default, `debug` in dev.
