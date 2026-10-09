<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Order\OrderStatusProviderInterface;

final class FixedOrderStatusProvider implements OrderStatusProviderInterface
{
    /**
     * @param  array<string, string> $statuses
     */
    public function __construct(private readonly array $statuses = [])
    {
    }

    public function all(): array
    {
        return $this->statuses;
    }
}
