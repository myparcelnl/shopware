<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Language;

use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\Locale\LocaleException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads the language from the Shopware context of the main request: the sales
 * channel language in the storefront, the content language in the admin API.
 */
final class ShopwareLocaleResolver implements LocaleResolverInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly LanguageLocaleCodeProvider $localeCodeProvider
    ) {
    }

    public function getLocale(): ?string
    {
        $context = $this->requestStack->getMainRequest()?->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        if (!$context instanceof Context) {
            return null;
        }

        try {
            return $this->localeCodeProvider->getLocaleForLanguageId($context->getLanguageId());
        } catch (LocaleException) {
            return null;
        }
    }
}
