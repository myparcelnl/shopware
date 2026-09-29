<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use MyParcelNL\Pdk\Base\Pdk;
use Psr\Log\LoggerInterface;

/**
 * Boots the PDK with the plug-in's own details, taken from Shopware rather than
 * hardcoded at the call site.
 *
 * The PDK always boots in development mode for now, because production mode
 * compiles the container, and that needs every PDK template contract bound
 * (order, cart, shipping method, webhooks, cron, endpoints, order status,
 * view), which happens in the adapter layer of epic INT-1748. Switch back to
 * following kernel.debug then.
 */
final class PdkInitializer
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ConfigStorageInterface $configStorage,
        private readonly LocaleResolverInterface $localeResolver,
        private readonly string $pluginVersion,
        private readonly string $appUrl
    ) {
    }

    /**
     * Safe to call repeatedly: PdkBootstrapper::boot() only builds once.
     */
    public function boot(): Pdk
    {
        PdkBootstrapper::setLogger($this->logger);
        PdkBootstrapper::setServices(new ShopwareServices($this->configStorage, $this->localeResolver));

        return PdkBootstrapper::boot(
            $this->pluginVersion,
            $this->getPluginPath(),
            $this->appUrl,
            Pdk::MODE_DEVELOPMENT
        );
    }

    private function getPluginPath(): string
    {
        return dirname(__DIR__, 2);
    }
}
