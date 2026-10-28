<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcel\Shopware\Pdk\Cache\CacheDirectory;
use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use MyParcel\Shopware\Pdk\Order\Contract\OrderStateTransitionerInterface;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Pdk\Product\Contract\ProductStorageInterface;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;
use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use MyParcelNL\Pdk\Base\Pdk;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Boots the PDK with the plug-in's own details, taken from Shopware rather than
 * hardcoded at the call site.
 *
 * The mode follows kernel.debug, the same as the WooCommerce plugin follows
 * WP_DEBUG. Production mode compiles the container into the PDK cache
 * directory. The placeholders in Pdk/Placeholder keep that possible until the
 * adapter layer of epic INT-1748 lands.
 *
 * When that directory is read-only, production mode runs without a compiled
 * container instead of failing on every boot.
 */
final class PdkInitializer
{
    /**
     * @param  EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ConfigStorageInterface $configStorage,
        private readonly LocaleResolverInterface $localeResolver,
        private readonly UrlResolverInterface $urlResolver,
        private readonly EntityRepository $orderRepository,
        private readonly ProductStorageInterface $productStorage,
        private readonly EuroCurrency $euroCurrency,
        private readonly OrderDataWriter $orderDataWriter,
        private readonly OrderStateTransitionerInterface $orderStateTransitioner,
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
        PdkBootstrapper::setServices(new ShopwareServices(
            $this->configStorage,
            $this->localeResolver,
            $this->urlResolver,
            $this->orderRepository,
            $this->productStorage,
            $this->euroCurrency,
            $this->orderDataWriter,
            $this->orderStateTransitioner
        ));

        if (!$this->debug && !CacheDirectory::isWritable(Pdk::getCacheDir())) {
            // The PDK has no setting for its cache directory, only this switch.
            // Debug, not warning: this runs on every request on such a host.
            // @TODO INT-2036: set the PDK cache directory to Shopware's cache
            // directory once the PDK has a setting for it, and remove this fallback.
            putenv('PDK_DISABLE_CACHE=1');
            $this->logger->debug('MyParcel: the PDK cache directory is read-only, so the PDK runs without a compiled container');
        }

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
