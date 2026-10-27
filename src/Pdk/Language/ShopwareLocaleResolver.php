<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Language;

use MyParcel\Shopware\Pdk\Http\RequestContext;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\System\Locale\LocaleException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads the language from the Shopware context of the main request: the
 * language of the admin user in the admin API, the sales channel language in
 * the storefront.
 */
final class ShopwareLocaleResolver implements LocaleResolverInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly AppLocaleProvider $localeProvider
    ) {
    }

    /**
     * Get the locale of the admin user in an admin API request, otherwise the
     * locale of the context language. Null outside a Shopware request, or when
     * the user or the language has no locale.
     */
    public function getLocale(): ?string
    {
        $context = RequestContext::fromRequestStack($this->requestStack);

        if (null === $context) {
            return null;
        }

        try {
            return $this->localeProvider->getLocaleFromContext($context);
        } catch (EntityNotFoundException|LocaleException) {
            return null;
        }
    }
}
