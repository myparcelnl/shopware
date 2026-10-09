<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use MyParcel\Shopware\Pdk\Order\OrderStatusProviderInterface;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;
use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use MyParcel\Shopware\Pdk\View\ViewResolverInterface;
use MyParcelNL\Pdk\Base\Pdk;
use Psr\Log\LoggerInterface;

/**
 * Boots the PDK with the plug-in's own details, taken from Shopware rather than
 * hardcoded at the call site.
 *
 * The mode follows kernel.debug, the same as the WooCommerce plugin follows
 * WP_DEBUG. Production mode compiles the container into the PDK cache
 * directory. The placeholders in Pdk/Placeholder keep that possible until the
 * adapter layer of epic INT-1748 lands.
 */
final class PdkInitializer
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ConfigStorageInterface $configStorage,
        private readonly LocaleResolverInterface $localeResolver,
        private readonly UrlResolverInterface $urlResolver,
        private readonly ViewResolverInterface $viewResolver,
        private readonly OrderStatusProviderInterface $orderStatusProvider,
        private readonly string $pluginVersion,
        private readonly string $appUrl,
        private readonly bool $debug
    ) {
    }

    /**
     * Safe to call repeatedly: PdkBootstrapper::boot() only builds once.
     */
    public function boot(): Pdk
    {
        PdkBootstrapper::setLogger($this->logger);
        PdkBootstrapper::setServices(
            new ShopwareServices(
                $this->configStorage,
                $this->localeResolver,
                $this->urlResolver,
                $this->viewResolver,
                $this->orderStatusProvider
            )
        );

        return PdkBootstrapper::boot(
            $this->pluginVersion,
            $this->getPluginPath(),
            $this->appUrl,
            $this->debug ? Pdk::MODE_DEVELOPMENT : Pdk::MODE_PRODUCTION
        );
    }

    private function getPluginPath(): string
    {
        return dirname(__DIR__, 2);
    }
}
