<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Admin;

/**
 * The PDK admin app is built separately from the Shopware admin bundle (it
 * brings its own Vue). bin/console assets:install copies it from
 * Resources/public/pdk.
 */
final class AdminAssets
{
    private const SCRIPTS = ['bundles/myparcelshopware/pdk/admin.iife.js'];

    private const STYLES = ['bundles/myparcelshopware/pdk/admin.css'];

    public function __construct(private readonly AssetUrlResolverInterface $urlResolver)
    {
    }

    /**
     * @return list<string>
     */
    public function getScripts(): array
    {
        return array_map($this->urlResolver->getUrl(...), self::SCRIPTS);
    }

    /**
     * @return list<string>
     */
    public function getStyles(): array
    {
        return array_map($this->urlResolver->getUrl(...), self::STYLES);
    }
}
