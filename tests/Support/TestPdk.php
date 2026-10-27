<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcelNL\Pdk\Base\Factory\PdkFactory;
use MyParcelNL\Pdk\Base\Pdk;
use MyParcelNL\Pdk\Facade\Pdk as PdkFacade;
use MyParcelNL\Pdk\Logger\Contract\PdkLoggerInterface;
use MyParcelNL\Pdk\Tests\Bootstrap\MockPdkFactory;
use MyParcelNL\Pdk\Tests\Bootstrap\TestBootstrapper;

/**
 * Boots the PDK's own mock environment, with an account that has carriers, so
 * tests can build PDK models. Development mode: nothing is compiled or cached.
 */
final class TestPdk
{
    /**
     * Boots a fresh PDK. Call it in every test that builds a PDK model.
     *
     * @param  array<string, mixed> $definitions php-di definitions that replace the mock ones
     */
    public static function boot(array $definitions = []): void
    {
        PdkFactory::setMode(Pdk::MODE_DEVELOPMENT);
        MockPdkFactory::create($definitions);
        TestBootstrapper::hasAccount();
    }

    /**
     * The log entries of one level, each as ['level', 'message', 'context'].
     *
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public static function logs(string $level): array
    {
        return PdkFacade::get(PdkLoggerInterface::class)->getLogs($level);
    }
}
