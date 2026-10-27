<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcelNL\Pdk\App\Order\Contract\OrderStatusServiceInterface;

final class FixedOrderStatusService implements OrderStatusServiceInterface
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

    public function updateStatus(array $orderIds, string $status): void
    {
    }
}
