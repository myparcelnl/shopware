<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Cart;

use MyParcelNL\Pdk\App\Cart\Model\PdkCart;
use MyParcelNL\Pdk\Facade\Settings;
use MyParcelNL\Pdk\Settings\Model\CheckoutSettings;

/**
 * Decides whether the consumer gets delivery options for a cart. The confirm
 * page shows the widget, and the cart charges the fees, only when this is true,
 * so the fees always match what the consumer saw (as in the WooCommerce plugin).
 */
final class DeliveryOptionsEligibility
{
    /**
     * False when the merchant turned the delivery options off, when a product
     * is not deliverable or has the delivery options disabled, or when the
     * merchant linked no package type to the chosen shipping method.
     */
    public static function isEligible(PdkCart $cart): bool
    {
        // A setting that was never saved is null. Its default is on, so only an
        // explicit false turns the delivery options off.
        if (false === Settings::get(CheckoutSettings::ENABLE_DELIVERY_OPTIONS, CheckoutSettings::ID)) {
            return false;
        }

        $shippingMethod = $cart->shippingMethod;

        return $shippingMethod->hasDeliveryOptions && !$shippingMethod->allowedPackageTypes->isEmpty();
    }
}
