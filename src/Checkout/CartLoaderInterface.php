<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Loads and saves the calculated cart of a sales channel context. Shopware's
 * CartService is final, so the plugin services depend on this interface instead.
 */
interface CartLoaderInterface
{
    /**
     * Returns the calculated cart of the context's token. Use it to read the
     * consumer's cart.
     */
    public function load(SalesChannelContext $context): Cart;

    /**
     * Calculates the cart again and saves it. Use it after a change to the cart,
     * such as a new choice of delivery options.
     */
    public function recalculate(Cart $cart, SalesChannelContext $context): Cart;
}
