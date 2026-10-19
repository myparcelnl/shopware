<?php

declare(strict_types=1);

namespace MyParcel\Shopware;

use Doctrine\DBAL\Connection;
use MyParcel\Shopware\Installer\CustomFieldInstaller;
use MyParcelNL\Pdk\Base\Pdk as BasePdk;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcel\Shopware\Pdk\PdkBootstrapper;
use MyParcel\Shopware\Pdk\Product\ProductCustomFields;
use MyParcel\Shopware\Pdk\Storage\SystemConfigStorage;
use RuntimeException;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\SystemConfig\CachedSystemConfigLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// The plugin ships its own (php-scoper prefixed) vendor directory with the
// PDK. Shopware only registers the plugin's PSR-4 prefix, so load it here.
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoloader)) {
    require_once $autoloader;
}

class MyParcelShopware extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registers Resources/config/packages/*.yaml, which declares the
        // "myparcel" Monolog channel. Bundle::build() does not do this by
        // itself; Shopware's own bundles call it explicitly too.
        $this->buildDefaultConfig($container);

        $container->setParameter('myparcel.plugin_version', $this->readVersion());
    }

    /**
     * Creates the custom field set for the product settings. The tables come from
     * the migrations in src/Migration.
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->bootPdkForLifecycle($installContext->getCurrentPluginVersion());
        $this->createCustomFieldInstaller()->install($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        // system_config keeps only what config.xml declares under
        // MyParcelShopware.config.*; the PDK's own keys, the API key among them,
        // are ours to remove. The raw DELETE bypasses SystemConfigService, so its
        // cache tag is not invalidated by it and we do that ourselves below.
        $connection = $this->container->get(Connection::class);
        $connection->executeStatement(
            'DELETE FROM system_config WHERE configuration_key LIKE :prefix',
            ['prefix' => SystemConfigStorage::KEY_PREFIX . '%']
        );

        $this->container->get(CacheInvalidator::class)->invalidate([CachedSystemConfigLoader::CACHE_TAG], true);

        $this->bootPdkForLifecycle($this->readVersion());
        $this->createCustomFieldInstaller()->uninstall($uninstallContext->getContext());
        $this->removeProductCustomFieldValues($connection);

        $connection->executeStatement('DROP TABLE IF EXISTS `myparcel_order_shipment`, `myparcel_order_data`');
    }

    /**
     * Clears the compiled PDK container, so the new plugin version does not
     * use the definitions of the old one.
     */
    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        // The PDK compiles its container in production and never invalidates it, so
        // after an update the definitions still describe the version being replaced.
        //
        // Booting in development mode on purpose: clearCache() is mode-independent,
        // but a production boot would compile a container from the old
        // definitions right before clearCache() deletes it. Development mode
        // skips compilation, so this reaches the facade without building what we
        // are about to delete.
        //
        // Not routed through PdkInitializer: Shopware updates inactive plugins too,
        // and then our services are not in its container.
        $this->bootPdkForLifecycle($updateContext->getUpdatePluginVersion());

        Pdk::clearCache();

        // New PDK settings get a field. Fields of settings the PDK dropped stay.
        $this->createCustomFieldInstaller()->install($updateContext->getContext());
    }

    /**
     * composer.json is the single source of truth for the version: Shopware reads
     * it from there too when refreshing the plugin list.
     */
    private function readVersion(): string
    {
        $file     = dirname(__DIR__) . '/composer.json';
        $contents = is_file($file) ? file_get_contents($file) : false;
        $decoded  = false === $contents ? null : json_decode($contents, true);

        if (!is_array($decoded) || !isset($decoded['version']) || !is_string($decoded['version'])) {
            throw new RuntimeException(sprintf('Cannot read the plugin version from %s', $file));
        }

        return $decoded['version'];
    }

    /**
     * Boots the PDK without the Shopware services: the plugin's own services are
     * not in the container while it is installed, updated or uninstalled. Nothing
     * here resolves them.
     */
    private function bootPdkForLifecycle(string $version): void
    {
        PdkBootstrapper::boot($version, dirname(__DIR__), '', BasePdk::MODE_DEVELOPMENT);
    }

    private function createCustomFieldInstaller(): CustomFieldInstaller
    {
        return new CustomFieldInstaller($this->container->get('custom_field_set.repository'));
    }

    /**
     * product.custom_fields is translated, so the values live in product_translation.
     * JSON_REMOVE ignores paths that do not exist.
     */
    private function removeProductCustomFieldValues(Connection $connection): void
    {
        $paths = array_map(
            static fn (string $key) => sprintf("'$.%s'", ProductCustomFields::fieldName($key)),
            ProductCustomFields::keys()
        );

        $connection->executeStatement(sprintf(
            'UPDATE `product_translation` SET `custom_fields` = JSON_REMOVE(`custom_fields`, %s) WHERE `custom_fields` IS NOT NULL',
            implode(', ', $paths)
        ));
    }
}
