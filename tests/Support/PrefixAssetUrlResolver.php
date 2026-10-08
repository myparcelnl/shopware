<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Admin\AssetUrlResolverInterface;

final class PrefixAssetUrlResolver implements AssetUrlResolverInterface
{
    public function getUrl(string $path): string
    {
        return 'https://shop.test/' . $path . '?1';
    }
}
