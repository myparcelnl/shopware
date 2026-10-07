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
            // TODO: follow kernel.debug again once the adapter layer of epic
            // INT-1748 binds every PDK template contract (order, cart, shipping
            // method, webhooks, cron, endpoints, order status, view). Production
            // mode compiles the container, and compilation fails while any
            // contract is unbound.
            Pdk::MODE_DEVELOPMENT
        );
    }

    private function getPluginPath(): string
    {
        return dirname(__DIR__, 2);
    }
}
