<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\View;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The Shopware admin is a single-page app: no PHP page tells the PDK where it
 * is. The view route sets the view on the request instead.
 */
final class RequestViewResolver implements ViewResolverInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * Reads the view from the main request. Null when the request is not the
     * view route, or when the attribute holds another value.
     */
    public function getView(): ?AdminView
    {
        $view = $this->requestStack->getMainRequest()?->attributes->get(AdminView::REQUEST_ATTRIBUTE);

        return $view instanceof AdminView ? $view : null;
    }
}
