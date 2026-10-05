<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Routing;

/**
 * Builds the absolute URL of a plugin route.
 *
 * A seam of our own rather than the Symfony router directly: the unit tests
 * run against the unscoped development vendor, which has no symfony/routing.
 */
interface UrlResolverInterface
{
    public function getAbsoluteUrl(string $routeName): string;
}
