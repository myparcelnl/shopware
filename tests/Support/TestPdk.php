<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use Closure;
use MyParcelNL\Pdk\Base\Concern\PdkInterface;
use MyParcelNL\Pdk\App\Account\Contract\PdkAccountRepositoryInterface;
use MyParcelNL\Pdk\Base\Factory\PdkFactory;
use MyParcelNL\Pdk\Base\Support\Collection;
use MyParcelNL\Pdk\Base\PdkBootstrapper;
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
        $pdk = MockPdkFactory::create($definitions);
        TestBootstrapper::hasAccount();

        self::markBooted($pdk);
    }

    /**
     * Makes PdkBootstrapper::boot() return the mock PDK. Without this, a class
     * that calls PdkInitializer::boot() would build the real PDK over the mock.
     */
    private static function markBooted(PdkInterface $pdk): void
    {
        Closure::bind(static function () use ($pdk): void {
            static::$initialized = true;
            static::$pdk         = $pdk;
        }, null, PdkBootstrapper::class)();
    }

    /**
     * Gives the account these subscription features, which decide the order mode.
     * Call it after boot().
     *
     * @param  string[] $features
     */
    public static function useSubscriptionFeatures(array $features): void
    {
        /** @var PdkAccountRepositoryInterface $accounts */
        $accounts = PdkFacade::get(PdkAccountRepositoryInterface::class);
        $account  = $accounts->getAccount();

        $account->subscriptionFeatures = new Collection($features);
        $accounts->store($account);
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
