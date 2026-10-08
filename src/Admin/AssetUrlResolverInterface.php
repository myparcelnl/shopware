<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Admin;

interface AssetUrlResolverInterface
{
    /**
     * @param  string $path relative to the public asset directory
     */
    public function getUrl(string $path): string;
}
