<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Api;

use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;
use MyParcelNL\Pdk\App\Api\Backend\AbstractPdkBackendEndpointService;

/**
 * Tells the admin app where the PDK backend endpoint is.
 *
 * The admin API needs a bearer token that only the browser has, so the admin
 * app adds the Authorization header itself (INT-1958). No headers are set here.
 */
final class BackendEndpointService extends AbstractPdkBackendEndpointService
{
    public function __construct(private readonly UrlResolverInterface $urlResolver)
    {
    }

    public function getBaseUrl(): string
    {
        return $this->urlResolver->getAbsoluteUrl(RouteName::ADMIN_PDK);
    }
}
