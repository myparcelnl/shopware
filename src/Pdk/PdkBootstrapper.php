<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk;

use MyParcel\Shopware\Pdk\Account\Repository\PdkAccountRepository;
use MyParcel\Shopware\Pdk\Api\BackendEndpointService;
use MyParcel\Shopware\Pdk\Api\FrontendEndpointService;
use MyParcel\Shopware\Pdk\Api\Guzzle7ClientAdapter;
use MyParcel\Shopware\Pdk\Cron\SynchronousCronService;
use MyParcel\Shopware\Pdk\Frontend\ViewService;
use MyParcel\Shopware\Pdk\Language\LanguageService;
use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use MyParcel\Shopware\Pdk\Logger\PdkLogger;
use MyParcel\Shopware\Pdk\Placeholder\PlaceholderCartRepository;
use MyParcel\Shopware\Pdk\Placeholder\PlaceholderOrderRepository;
use MyParcel\Shopware\Pdk\Placeholder\PlaceholderOrderStatusService;
use MyParcel\Shopware\Pdk\Placeholder\PlaceholderShippingMethodRepository;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;
use MyParcel\Shopware\Pdk\Settings\Repository\PdkSettingsRepository;
use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use MyParcel\Shopware\Pdk\View\ViewResolverInterface;
use MyParcel\Shopware\Pdk\Webhook\Repository\PdkWebhooksRepository;
use MyParcel\Shopware\Pdk\Webhook\Service\PdkWebhookService;
use MyParcelNL\Pdk\Api\Contract\ClientAdapterInterface;
use MyParcelNL\Pdk\App\Account\Contract\PdkAccountRepositoryInterface;
use MyParcelNL\Pdk\App\Api\Contract\BackendEndpointServiceInterface;
use MyParcelNL\Pdk\App\Api\Contract\FrontendEndpointServiceInterface;
use MyParcelNL\Pdk\App\Cart\Contract\PdkCartRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Contract\OrderStatusServiceInterface;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\App\ShippingMethod\Contract\PdkShippingMethodRepositoryInterface;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhookServiceInterface;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhooksRepositoryInterface;
use MyParcelNL\Pdk\Base\Contract\CronServiceInterface;
use MyParcelNL\Pdk\Base\PdkBootstrapper as AbstractPdkBootstrapper;
use MyParcelNL\Pdk\Frontend\Contract\ViewServiceInterface;
use MyParcelNL\Pdk\Language\Contract\LanguageServiceInterface;
use MyParcelNL\Pdk\Logger\Contract\PdkLoggerInterface;
use MyParcelNL\Pdk\Settings\Contract\PdkSettingsRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Hands Shopware-owned services to the PDK.
 *
 * The PDK builds its own php-di container, so anything Shopware constructs has to
 * be reachable from there. Call the setters before boot(): the container reads
 * them through the static factories below when it first resolves an entry.
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
     * Container factory. Static, because php-di cannot compile an object value,
     * and the compiled container moves a closure body into another class.
     *
     * @internal Only the PDK container calls this.
     */
    public static function createLogger(): PdkLogger
    {
        return new PdkLogger(self::$shopwareLogger ?? new NullLogger());
    }

    /**
     * @internal Only the PDK container calls this.
     */
    public static function getConfigStorage(): ConfigStorageInterface
    {
        return self::getShopwareServices()->configStorage;
    }

    /**
     * @internal Only the PDK container calls this.
     */
    public static function getLocaleResolver(): LocaleResolverInterface
    {
        return self::getShopwareServices()->localeResolver;
    }

    /**
     * @internal Only the PDK container calls this.
     */
    public static function getUrlResolver(): UrlResolverInterface
    {
        return self::getShopwareServices()->urlResolver;
    }

    /**
     * @internal Only the PDK container calls this.
     */
    public static function getViewResolver(): ViewResolverInterface
    {
        return self::getShopwareServices()->viewResolver;
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
        // A compiled container is built once and cached, so its definitions must
        // not depend on which caller booted first. The Shopware-service entries
        // are therefore always bound, and fail only when something resolves them
        // without setServices(). MyParcelShopware::update() boots without the
        // services, but its clearCache() call resolves none of these entries.
        return [
            // The PDK's own template binds PdkLoggerInterface to the (deprecated)
            // scoped PSR key, so both have to give the same instance. The
            // prefixed name is what exists at runtime: config/pdk.php is not
            // scoped, and neither is our src.
            PdkLoggerInterface::class                   => \_MyParcel\DI\factory([PdkBootstrapper::class, 'createLogger']),
            \_MyParcel\Psr\Log\LoggerInterface::class => \_MyParcel\DI\get(PdkLoggerInterface::class),
            ClientAdapterInterface::class               => \_MyParcel\DI\autowire(Guzzle7ClientAdapter::class),
            CronServiceInterface::class                 => \_MyParcel\DI\autowire(SynchronousCronService::class),
            // The PDK puts "myparcelcom_" before every settings key by default.
            // SystemConfigStorage already prefixes with MyParcelShopware.pdk., so
            // keep one prefix only.
            'settingKeyPrefix'                          => \_MyParcel\DI\value(''),

            // Placeholders for the contracts that the adapter layer of epic
            // INT-1748 adds. Production mode compiles the container, and php-di
            // cannot compile an interface without a concrete class.
            PdkOrderRepositoryInterface::class          => \_MyParcel\DI\autowire(PlaceholderOrderRepository::class),
            PdkCartRepositoryInterface::class           => \_MyParcel\DI\autowire(PlaceholderCartRepository::class),
            PdkShippingMethodRepositoryInterface::class => \_MyParcel\DI\autowire(PlaceholderShippingMethodRepository::class),
            OrderStatusServiceInterface::class          => \_MyParcel\DI\autowire(PlaceholderOrderStatusService::class),

            ConfigStorageInterface::class               => \_MyParcel\DI\factory([PdkBootstrapper::class, 'getConfigStorage']),
            LocaleResolverInterface::class              => \_MyParcel\DI\factory([PdkBootstrapper::class, 'getLocaleResolver']),
            UrlResolverInterface::class                 => \_MyParcel\DI\factory([PdkBootstrapper::class, 'getUrlResolver']),
            ViewResolverInterface::class                => \_MyParcel\DI\factory([PdkBootstrapper::class, 'getViewResolver']),
            ViewServiceInterface::class                 => \_MyParcel\DI\autowire(ViewService::class),
            PdkSettingsRepositoryInterface::class       => \_MyParcel\DI\autowire(PdkSettingsRepository::class),
            PdkAccountRepositoryInterface::class        => \_MyParcel\DI\autowire(PdkAccountRepository::class),
            LanguageServiceInterface::class             => \_MyParcel\DI\autowire(LanguageService::class),
            PdkWebhooksRepositoryInterface::class       => \_MyParcel\DI\autowire(PdkWebhooksRepository::class),
            PdkWebhookServiceInterface::class           => \_MyParcel\DI\autowire(PdkWebhookService::class),
            BackendEndpointServiceInterface::class      => \_MyParcel\DI\autowire(BackendEndpointService::class),
            FrontendEndpointServiceInterface::class     => \_MyParcel\DI\autowire(FrontendEndpointService::class),
        ];
    }

    private static function getShopwareServices(): ShopwareServices
    {
        return self::$shopwareServices
            ?? throw new \LogicException('PdkBootstrapper::setServices() was not called');
    }
}
