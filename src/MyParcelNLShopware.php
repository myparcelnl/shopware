<?php

declare(strict_types=1);

namespace MyParcelNL\Shopware;

use Shopware\Core\Framework\Plugin;
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
    }
}
