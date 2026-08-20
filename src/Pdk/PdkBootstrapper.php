<?php

declare(strict_types=1);

namespace MyParcelNL\Shopware\Pdk;

use MyParcelNL\Pdk\Base\PdkBootstrapper as AbstractPdkBootstrapper;
use MyParcelNL\Pdk\Logger\Contract\PdkLoggerInterface;
use MyParcelNL\Shopware\Pdk\Logger\PdkLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Hands Shopware-owned services to the PDK.
 *
 * The PDK builds its own php-di container, so anything Shopware constructs has to
 * be registered here before boot() runs. Call the setters first; afterwards the
 * definitions are compiled and later values are ignored.
 */
final class PdkBootstrapper extends AbstractPdkBootstrapper
{
    private static ?LoggerInterface $shopwareLogger = null;

    /**
     * A static seam because the PDK declares boot() final and static, so there is
     * no instance to inject into. Shopware autowires the logger into the caller.
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$shopwareLogger = $logger;
    }

    /**
     * These definitions are merged last, so they win over config/pdk.php.
     *
     * @return array<string, mixed>
     */
    protected function getAdditionalConfig(
        string $name,
        string $title,
        string $version,
        string $path,
        string $url
    ): array {
        $logger = new PdkLogger(self::$shopwareLogger ?? new NullLogger());

        return [
            // The PDK's own template binds PdkLoggerInterface to the (deprecated)
            // scoped PSR key, so both have to point at the same instance. The
            // prefixed name is what exists at runtime: config/pdk.php is not
            // scoped, and neither is our src.
            \_MyParcelNL\Psr\Log\LoggerInterface::class => \_MyParcelNL\DI\value($logger),
            PdkLoggerInterface::class                   => \_MyParcelNL\DI\value($logger),
        ];
    }
}
