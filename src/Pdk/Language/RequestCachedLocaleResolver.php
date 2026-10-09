<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Language;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use WeakMap;

/**
 * The PDK asks for the language on every translation lookup, and the settings
 * view has hundreds of them. The locale of a request does not change, so ask
 * once per main request. Keyed on the request object, so a long-running worker
 * never gives one request the locale of another.
 */
final class RequestCachedLocaleResolver implements LocaleResolverInterface
{
    /**
     * @var WeakMap<Request, array{locale: ?string}>
     */
    private WeakMap $locales;

    public function __construct(
        private readonly LocaleResolverInterface $resolver,
        private readonly RequestStack $requestStack
    ) {
        $this->locales = new WeakMap();
    }

    public function getLocale(): ?string
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request) {
            return $this->resolver->getLocale();
        }

        $this->locales[$request] ??= ['locale' => $this->resolver->getLocale()];

        return $this->locales[$request]['locale'];
    }
}
