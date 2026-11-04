<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\Cart\DeliveryOptionsEligibility;
use MyParcelNL\Pdk\App\Cart\Model\PdkCart;
use MyParcelNL\Pdk\Context\Context;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Facade\Settings;
use MyParcelNL\Pdk\Frontend\Service\FrontendRenderService as PdkFrontendRenderService;
use MyParcelNL\Pdk\Settings\Model\CheckoutSettings;

/**
 * The PDK template starts each component with an inline script. The admin CSP
 * blocks inline scripts ('strict-dynamic' makes the browser ignore
 * 'unsafe-inline'), and HTML inserted with innerHTML does not run them either.
 * This template names the component in a data attribute instead, and the admin
 * app renders it.
 *
 * The checkout has its own markup: the checkout scripts of js-pdk read the
 * context from data-context on the wrapper and mount the widget in
 * #myparcel-delivery-options, as in the WooCommerce and PrestaShop plugins.
 */
final class FrontendRenderService extends PdkFrontendRenderService
{
    private const RENDER_TEMPLATE = '<div data-pdk-component="__COMPONENT__" data-pdk-context="__CONTEXT__" id="__ID__"></div>';

    private const DELIVERY_OPTIONS_TEMPLATE = '<div id="mypa-delivery-options-wrapper" class="%s__delivery-options" data-context="%s">%s<div id="myparcel-delivery-options"></div></div>';

    /**
     * Renders the wrapper of the delivery options widget with the checkout
     * context. Gives an empty string outside the checkout and when the cart gets
     * no delivery options (DeliveryOptionsEligibility). The shipping method only
     * changes with a page reload, so the page never needs a hidden widget.
     * Building the context calls the MyParcel API and can throw: the caller
     * catches that, so the checkout keeps working.
     */
    public function renderDeliveryOptions(PdkCart $cart): string
    {
        if (!$this->shouldRender(self::COMPONENT_DELIVERY_OPTIONS) || !DeliveryOptionsEligibility::isEligible($cart)) {
            return '';
        }

        $context = $this->contextService->createContexts([Context::ID_CHECKOUT], ['cart' => $cart]);

        $customCss = (string) Settings::get(CheckoutSettings::DELIVERY_OPTIONS_CUSTOM_CSS, CheckoutSettings::ID);

        return sprintf(
            self::DELIVERY_OPTIONS_TEMPLATE,
            Pdk::getAppInfo()->name,
            $this->encodeContext($context),
            // The merchant writes this CSS. "</" would close the style element.
            '' === trim($customCss) ? '' : sprintf('<style>%s</style>', str_replace('</', '<\/', $customCss))
        );
    }

    protected function getRenderTemplate(): string
    {
        return self::RENDER_TEMPLATE;
    }
}
