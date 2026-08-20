<?php

declare(strict_types=1);

/**
 * Platform-specific PDK DI overrides.
 *
 * Services that Shopware constructs cannot be defined here — this file is
 * loaded by the PDK's own php-di container and has no access to Shopware's.
 * Those go through MyParcel\Shopware\Pdk\PdkBootstrapper::getAdditionalConfig(),
 * whose definitions are merged after this file and therefore win.
 *
 * NB: this file is not run through php-scoper, so any key naming a third-party
 * interface needs the _MyParcel prefix spelled out.
 */
return [];
