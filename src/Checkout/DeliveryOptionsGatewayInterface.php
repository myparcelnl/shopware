<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * What the checkout asks the PDK about delivery options. A seam of our own so
 * the route and the cart processor can be tested without booting the PDK, which
 * needs the Shopware container.
 */
interface DeliveryOptionsGatewayInterface
{
    /**
     * Turns the output of the widget into the form that the PDK stores on an
     * order. Use it before the choice goes on the cart.
     *
     * @param  array<string, mixed> $input
     *
     * @return array<string, mixed> DeliveryOptions::toStorableArray()
     *
     * @throws \Throwable when the PDK cannot read the input, for example an unknown carrier
     */
    public function normalize(array $input, SalesChannelContext $context): array;

    /**
     * The fees of the chosen delivery options for the cart. Empty when the cart
     * gets no delivery options (DeliveryOptionsEligibility).
     *
     * @param  array<string, mixed> $deliveryOptions DeliveryOptions::toStorableArray()
     *
     * @return list<DeliveryOptionsFee>
     */
    public function fees(Cart $cart, SalesChannelContext $context, array $deliveryOptions): array;
}
