<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcel\Shopware\Pdk\Account\Repository\PdkAccountRepository;
use MyParcel\Shopware\Pdk\Api\BackendEndpointService;
use MyParcel\Shopware\Pdk\Api\FrontendEndpointService;
use MyParcel\Shopware\Pdk\Api\Guzzle7ClientAdapter;
use MyParcel\Shopware\Pdk\Cron\SynchronousCronService;
use MyParcel\Shopware\Pdk\Language\LanguageService;
use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use MyParcel\Shopware\Pdk\Logger\PdkLogger;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;
use MyParcel\Shopware\Pdk\Settings\Repository\PdkSettingsRepository;
use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use MyParcel\Shopware\Pdk\Webhook\Repository\PdkWebhooksRepository;
use MyParcel\Shopware\Pdk\Webhook\Service\PdkWebhookService;
use MyParcelNL\Pdk\Api\Contract\ClientAdapterInterface;
use MyParcelNL\Pdk\App\Account\Contract\PdkAccountRepositoryInterface;
use MyParcelNL\Pdk\App\Api\Contract\BackendEndpointServiceInterface;
use MyParcelNL\Pdk\App\Api\Contract\FrontendEndpointServiceInterface;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhookServiceInterface;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhooksRepositoryInterface;
use MyParcelNL\Pdk\Base\Contract\CronServiceInterface;
use MyParcelNL\Pdk\Base\PdkBootstrapper as AbstractPdkBootstrapper;
use MyParcelNL\Pdk\Language\Contract\LanguageServiceInterface;
use MyParcelNL\Pdk\Logger\Contract\PdkLoggerInterface;
use MyParcelNL\Pdk\Settings\Contract\PdkSettingsRepositoryInterface;
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

    private static ?ShopwareServices $shopwareServices = null;

    /**
     * A static seam because the PDK declares boot() final and static, so there is
     * no instance to inject into. Shopware autowires the logger into the caller.
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$shopwareLogger = $logger;
    }

    /**
     * The same kind of seam as setLogger(), for the services behind the settings,
     * account, language, webhook and endpoint contracts.
     */
    public static function setServices(ShopwareServices $services): void
    {
        self::$shopwareServices = $services;
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

        $config = [
            // The PDK's own template binds PdkLoggerInterface to the (deprecated)
            // scoped PSR key, so both have to point at the same instance. The
            // prefixed name is what exists at runtime: config/pdk.php is not
            // scoped, and neither is our src.
            \_MyParcel\Psr\Log\LoggerInterface::class => \_MyParcel\DI\value($logger),
            PdkLoggerInterface::class                   => \_MyParcel\DI\value($logger),
            ClientAdapterInterface::class               => \_MyParcel\DI\autowire(Guzzle7ClientAdapter::class),
            CronServiceInterface::class                 => \_MyParcel\DI\autowire(SynchronousCronService::class),
            // The PDK puts "myparcelcom_" before every settings key by default.
            // SystemConfigStorage already prefixes with MyParcelShopware.pdk., so
            // keep one prefix only.
            'settingKeyPrefix'                          => \_MyParcel\DI\value(''),
        ];

        // Without the Shopware services, e.g. in MyParcelShopware::update(), the
        // PDK keeps its template defaults for these contracts.
        if (null === self::$shopwareServices) {
            return $config;
        }

        return $config + [
            ConfigStorageInterface::class           => \_MyParcel\DI\value(self::$shopwareServices->configStorage),
            LocaleResolverInterface::class          => \_MyParcel\DI\value(self::$shopwareServices->localeResolver),
            UrlResolverInterface::class             => \_MyParcel\DI\value(self::$shopwareServices->urlResolver),
            PdkSettingsRepositoryInterface::class   => \_MyParcel\DI\autowire(PdkSettingsRepository::class),
            PdkAccountRepositoryInterface::class    => \_MyParcel\DI\autowire(PdkAccountRepository::class),
            LanguageServiceInterface::class         => \_MyParcel\DI\autowire(LanguageService::class),
            PdkWebhooksRepositoryInterface::class   => \_MyParcel\DI\autowire(PdkWebhooksRepository::class),
            PdkWebhookServiceInterface::class       => \_MyParcel\DI\autowire(PdkWebhookService::class),
            BackendEndpointServiceInterface::class  => \_MyParcel\DI\autowire(BackendEndpointService::class),
            FrontendEndpointServiceInterface::class => \_MyParcel\DI\autowire(FrontendEndpointService::class),
        ];
    }
}
