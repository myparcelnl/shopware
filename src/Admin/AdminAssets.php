<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Admin;

use Symfony\Component\Asset\Packages;

/**
 * The PDK admin app is built separately from the Shopware admin bundle (it
 * brings its own Vue). bin/console assets:install copies it from
 * Resources/public/pdk.
 */
final class AdminAssets
{
    /**
     * Shopware registers one asset package per bundle. It knows the asset host
     * and adds a version, so a new build is never served from cache.
     */
    private const PACKAGE = '@MyParcelShopware';

    private const SCRIPTS = ['pdk/admin.iife.js'];

    private const STYLES = ['pdk/admin.css'];

    public function __construct(private readonly Packages $packages)
    {
    }

    /**
     * Get the script URLs that the admin module loads before it mounts the PDK
     * app.
     *
     * @return list<string>
     */
    public function getScripts(): array
    {
        return $this->getUrls(self::SCRIPTS);
    }

    /**
     * Get the stylesheet URLs that the admin module loads before it mounts the
     * PDK app.
     *
     * @return list<string>
     */
    public function getStyles(): array
    {
        return $this->getUrls(self::STYLES);
    }

    /**
     * @param  list<string> $paths
     *
     * @return list<string>
     */
    private function getUrls(array $paths): array
    {
        return array_map(fn (string $path): string => $this->packages->getUrl($path, self::PACKAGE), $paths);
    }
}
