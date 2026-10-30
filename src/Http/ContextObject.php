<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Http;

use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads the Shopware context object (PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)
 * that the admin API and the storefront put on the main request. Not the
 * Symfony routing RequestContext.
 *
 * @TODO INT-2151: cover this class with an integration test in a Shopware installation.
 */
final class ContextObject
{
    /**
     * Get the Shopware context of the main request. Null outside a Shopware
     * request, for example on the console.
     */
    public static function fromMainRequest(RequestStack $requestStack): ?Context
    {
        $context = $requestStack->getMainRequest()?->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        return $context instanceof Context ? $context : null;
    }
}
