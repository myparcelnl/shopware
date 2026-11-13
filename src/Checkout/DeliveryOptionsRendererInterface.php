<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * A seam of our own so the confirm page subscriber can be tested without booting
 * the PDK, which needs the Shopware container.
 */
interface DeliveryOptionsRendererInterface
{
    /**
     * Renders the delivery options for the cart on the confirm page. Gives null
     * when the PDK renders nothing: delivery options off, or not a checkout page.
     */
    public function render(Cart $cart, SalesChannelContext $context): ?DeliveryOptionsView;
}
