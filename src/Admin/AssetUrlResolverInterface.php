<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Admin;

interface AssetUrlResolverInterface
{
    /**
     * The public URL of an asset, with the version that busts the browser cache.
     *
     * @param  string $path relative to the public asset directory
     */
    public function getUrl(string $path): string;
}
