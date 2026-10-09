<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Admin;

use Symfony\Component\Asset\PackageInterface;

/**
 * Uses the Shopware asset package, which knows the asset host and adds the
 * last-modified time to the URL, so a new build is never served from cache.
 */
final class ShopwareAssetUrlResolver implements AssetUrlResolverInterface
{
    public function __construct(private readonly PackageInterface $package)
    {
    }

    public function getUrl(string $path): string
    {
        return $this->package->getUrl($path);
    }
}
