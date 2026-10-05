<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Api;

use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;
use MyParcelNL\Pdk\App\Api\Frontend\AbstractFrontendEndpointService;

/**
 * Tells the checkout where the PDK frontend endpoint is.
 */
final class FrontendEndpointService extends AbstractFrontendEndpointService
{
    public function __construct(private readonly UrlResolverInterface $urlResolver)
    {
    }

    public function getBaseUrl(): string
    {
        return $this->urlResolver->getAbsoluteUrl(RouteName::STOREFRONT_PDK);
    }
}
