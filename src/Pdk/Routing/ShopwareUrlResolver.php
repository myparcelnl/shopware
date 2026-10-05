<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Routing;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Uses the Shopware router, so the URL follows the host and the base path of
 * the current request.
 */
final class ShopwareUrlResolver implements UrlResolverInterface
{
    public function __construct(private readonly UrlGeneratorInterface $router)
    {
    }

    public function getAbsoluteUrl(string $routeName): string
    {
        return $this->router->generate($routeName, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
