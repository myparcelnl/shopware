<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use InvalidArgumentException;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\Context\Context;
use MyParcelNL\Pdk\Frontend\Service\FrontendRenderService as PdkFrontendRenderService;

/**
 * The PDK template starts each component with an inline script. The admin CSP
 * blocks inline scripts ('strict-dynamic' makes the browser ignore
 * 'unsafe-inline'), and HTML inserted with innerHTML does not run them either.
 * This template names the component in a data attribute instead, and the admin
 * app renders it.
 *
 * The admin app also renders the variant component on its own, without the
 * parent product component before it. Its settings view needs the product
 * settings view context for that.
 */
final class FrontendRenderService extends PdkFrontendRenderService
{
    private const RENDER_TEMPLATE = '<div data-pdk-component="__COMPONENT__" data-pdk-context="__CONTEXT__" id="__ID__"></div>';

    /**
     * Renders the variant settings with the product settings view context.
     * Use it for a variant: the PDK default leaves this context out, and the
     * component fails without it.
     */
    public function renderChildProductSettings(PdkProduct $product): string
    {
        if (! $product->parent) {
            throw new InvalidArgumentException('Product is not a child product');
        }

        return $this->renderComponent(
            self::COMPONENT_CHILD_PRODUCT_SETTINGS,
            [Context::ID_PRODUCT_DATA, Context::ID_PRODUCT_SETTINGS_VIEW],
            ['product' => $product]
        );
    }

    protected function getRenderTemplate(): string
    {
        return self::RENDER_TEMPLATE;
    }
}
