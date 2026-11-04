<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Checkout\DeliveryOptionsRendererInterface;
use MyParcel\Shopware\Checkout\DeliveryOptionsView;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Throwable;

/**
 * Gives a fixed view, or throws, and records the carts it rendered.
 */
final class FixedDeliveryOptionsRenderer implements DeliveryOptionsRendererInterface
{
    /**
     * @var list<array{Cart, SalesChannelContext}>
     */
    public array $rendered = [];

    public function __construct(private readonly ?DeliveryOptionsView $view = null, private readonly ?Throwable $error = null)
    {
    }

    public function render(Cart $cart, SalesChannelContext $context): ?DeliveryOptionsView
    {
        if (null !== $this->error) {
            throw $this->error;
        }

        $this->rendered[] = [$cart, $context];

        return $this->view;
    }
}
