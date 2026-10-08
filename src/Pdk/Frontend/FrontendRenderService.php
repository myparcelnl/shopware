<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcelNL\Pdk\Frontend\Service\FrontendRenderService as PdkFrontendRenderService;

/**
 * The PDK template starts each component with an inline script. The admin CSP
 * blocks inline scripts ('strict-dynamic' makes the browser ignore
 * 'unsafe-inline'), and HTML inserted with innerHTML does not run them either.
 * This template names the component in a data attribute instead, and the admin
 * app renders it.
 */
final class FrontendRenderService extends PdkFrontendRenderService
{
    private const RENDER_TEMPLATE = '<div data-pdk-component="__COMPONENT__" data-pdk-context="__CONTEXT__" id="__ID__"></div>';

    protected function getRenderTemplate(): string
    {
        return self::RENDER_TEMPLATE;
    }
}
