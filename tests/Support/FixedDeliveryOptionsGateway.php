<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Checkout\DeliveryOptionsFee;
use MyParcel\Shopware\Checkout\DeliveryOptionsGatewayInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Throwable;

/**
 * Gives fixed fees and passes the input through as the normalized choice, or
 * throws. Records what it was asked.
 */
final class FixedDeliveryOptionsGateway implements DeliveryOptionsGatewayInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $normalized = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $feesAskedFor = [];

    /**
     * @param  list<DeliveryOptionsFee> $fees
     */
    public function __construct(private readonly array $fees = [], private readonly ?Throwable $error = null)
    {
    }

    public function normalize(array $input, SalesChannelContext $context): array
    {
        if (null !== $this->error) {
            throw $this->error;
        }

        $this->normalized[] = $input;

        return $input + ['normalized' => true];
    }

    public function fees(Cart $cart, SalesChannelContext $context, array $deliveryOptions): array
    {
        if (null !== $this->error) {
            throw $this->error;
        }

        $this->feesAskedFor[] = $deliveryOptions;

        return $this->fees;
    }
}
