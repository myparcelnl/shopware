<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Language;

/**
 * The locale of the current Shopware request, as an IETF code such as de-DE.
 *
 * A seam of our own so the language service can be tested without Shopware.
 */
interface LocaleResolverInterface
{
    /**
     * @return null|string null outside a request, e.g. on the console
     */
    public function getLocale(): ?string;
}
