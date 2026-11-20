<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * A CartService that gives one fixed cart, and records the contexts it was asked
 * for and the carts it recalculated. CartService is @final only in its docblock,
 * so a test can extend it. The constructor does not call the parent: a real
 * CartService needs the whole cart pipeline.
 */
final class FixedCartService extends CartService
{
    /**
     * @var list<SalesChannelContext>
     */
    public array $contexts = [];

    /**
     * @var list<Cart>
     */
    public array $recalculated = [];

    public function __construct(private readonly ?Cart $fixedCart = null)
    {
    }

    public function getCart(string $token, SalesChannelContext $context, bool $caching = true, bool $taxed = false): Cart
    {
        $this->contexts[] = $context;

        return $this->fixedCart ?? new Cart($token);
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        $this->recalculated[] = $cart;

        return $cart;
    }
}
