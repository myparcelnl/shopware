<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Api\BackendEndpointService;
use MyParcel\Shopware\Pdk\Api\FrontendEndpointService;
use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcel\Shopware\Tests\Support\FixedUrlResolver;
use MyParcelNL\Pdk\App\Api\Contract\BackendEndpointServiceInterface;
use MyParcelNL\Pdk\App\Api\Contract\FrontendEndpointServiceInterface;

function endpointUrls(): FixedUrlResolver
{
    return new FixedUrlResolver([
        RouteName::ADMIN_PDK      => 'https://shop.test/api/_action/myparcel/pdk',
        RouteName::STOREFRONT_PDK => 'https://shop.test/myparcel/pdk',
    ]);
}

it('is a pdk backend endpoint service', function () {
    expect(new BackendEndpointService(endpointUrls()))->toBeInstanceOf(BackendEndpointServiceInterface::class);
});

it('points the admin app at the admin route', function () {
    expect((new BackendEndpointService(endpointUrls()))->getBaseUrl())
        ->toBe('https://shop.test/api/_action/myparcel/pdk');
});

it('is a pdk frontend endpoint service', function () {
    expect(new FrontendEndpointService(endpointUrls()))->toBeInstanceOf(FrontendEndpointServiceInterface::class);
});

it('points the checkout at the storefront route', function () {
    expect((new FrontendEndpointService(endpointUrls()))->getBaseUrl())
        ->toBe('https://shop.test/myparcel/pdk');
});
