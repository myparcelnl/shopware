<?php

declare(strict_types=1);

namespace MyParcelNL\Shopware;

use MyParcelNL\Shopware\Pdk\PdkContainerCache;
use RuntimeException;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// The plugin ships its own (php-scoper prefixed) vendor directory with the
// PDK. Shopware only registers the plugin's PSR-4 prefix, so load it here.
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoloader)) {
    require_once $autoloader;
}

class MyParcelNLShopware extends Plugin
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

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        // The PDK compiled its container for the version we are replacing.
        PdkContainerCache::clear();
    }

    /**
     * composer.json is the single source of truth for the version: Shopware reads
     * it from there too when refreshing the plug-in list.
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
