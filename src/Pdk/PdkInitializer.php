<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcelNL\Pdk\Base\Pdk;
use Psr\Log\LoggerInterface;

/**
 * Boots the PDK with the plug-in's own details, taken from Shopware rather than
 * hardcoded at the call site.
 *
 * The mode follows Shopware's debug flag: in development the PDK skips compiling
 * its container, in production it compiles to vendor/myparcelnl/pdk/.cache.
 */
final class PdkInitializer
{
    public function __construct(
        private readonly LoggerInterface $logger,
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
