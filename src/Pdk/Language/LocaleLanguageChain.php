<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Language;

/**
 * The admin context holds the content language. Texts that the screen shows,
 * such as the names of the order states, must follow the language of the user.
 */
final class LocaleLanguageChain
{
    /**
     * Puts the language of the locale in front of the request chain. Returns the
     * request chain unchanged when the shop has no language for the locale.
     *
     * @param  non-empty-list<string> $requestChain the language chain of the request, used as fallback
     *
     * @return non-empty-list<string>
     */
    public static function build(array $requestChain, ?string $locale, LanguageIdResolverInterface $resolver): array
    {
        $languageId = null === $locale ? null : $resolver->getLanguageId($locale);

        if (null === $languageId) {
            return $requestChain;
        }

        return array_values(array_unique([$languageId, ...$requestChain]));
    }
}
