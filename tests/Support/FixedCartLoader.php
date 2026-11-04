<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Checkout\CartLoaderInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Gives one fixed cart, and records the contexts it was asked for and the carts
 * it recalculated.
 */
final class FixedCartLoader implements CartLoaderInterface
{
    /**
     * @var list<SalesChannelContext>
     */
    public array $contexts = [];

    /**
     * @var list<Cart>
     */
    public array $recalculated = [];

    public function __construct(private readonly ?Cart $cart = null)
    {
    }

    public function load(SalesChannelContext $context): Cart
    {
        $this->contexts[] = $context;

        return $this->cart ?? new Cart($context->getToken());
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        $this->recalculated[] = $cart;

        return $cart;
    }
}
