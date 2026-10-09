<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use InvalidArgumentException;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;

/**
 * Answers from a fixed map, so a test can see which route a service asked for.
 */
final class FixedUrlResolver implements UrlResolverInterface
{
    /**
     * @param  array<string, string> $urls route name => absolute URL
     */
    public function __construct(private readonly array $urls)
    {
    }

    public function getAbsoluteUrl(string $routeName): string
    {
        if (!isset($this->urls[$routeName])) {
            throw new InvalidArgumentException(sprintf('No URL for route %s', $routeName));
        }

        return $this->urls[$routeName];
    }
}
