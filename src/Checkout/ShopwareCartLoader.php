<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Loads and saves the cart through Shopware's CartService, which keeps the
 * calculated cart of the request in memory.
 */
final class ShopwareCartLoader implements CartLoaderInterface
{
    public function __construct(private readonly CartService $cartService)
    {
    }

    public function load(SalesChannelContext $context): Cart
    {
        return $this->cartService->getCart($context->getToken(), $context);
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        return $this->cartService->recalculate($cart, $context);
    }
}
