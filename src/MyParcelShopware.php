<?php

declare(strict_types=1);

namespace MyParcel\Shopware;

use Doctrine\DBAL\Connection;
use MyParcelNL\Pdk\Base\Pdk as BasePdk;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcel\Shopware\Pdk\PdkBootstrapper;
use MyParcel\Shopware\Pdk\Storage\SystemConfigStorage;
use RuntimeException;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
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

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        // system_config keeps only what config.xml declares under
        // MyParcelShopware.config.*; the PDK's own keys, the API key among them,
        // are ours to remove.
        $this->container->get(Connection::class)->executeStatement(
            'DELETE FROM system_config WHERE configuration_key LIKE :prefix',
            ['prefix' => SystemConfigStorage::KEY_PREFIX . '%']
        );
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        // The PDK compiles its container in production and never invalidates it, so
        // after an update the definitions still describe the version being replaced.
        //
        // Booting in development mode on purpose: clearCache() is mode-independent,
        // but a production boot would compile the container first, and compilation
        // requires every PDK template contract to be instantiable — which it is not
        // until the adapter layer lands. Development mode skips compilation, so this
        // reaches the facade without building what we are about to delete.
        //
        // Not routed through PdkInitializer: Shopware updates inactive plugins too,
        // and then our services are not in its container.
        PdkBootstrapper::boot(
            $updateContext->getUpdatePluginVersion(),
            dirname(__DIR__),
            '',
            BasePdk::MODE_DEVELOPMENT
        );

        Pdk::clearCache();
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
}
