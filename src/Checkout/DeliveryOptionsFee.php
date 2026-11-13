<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

/**
 * One fee of the chosen delivery options, as the merchant set it: net, in euro.
 */
final class DeliveryOptionsFee
{
    public const PICKUP_ID = 'delivery_type_pickup';

    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly float $amount
    ) {
    }
}
