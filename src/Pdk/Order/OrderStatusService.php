<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order;

use MyParcel\Shopware\Pdk\Placeholder\NotImplementedException;
use MyParcelNL\Pdk\App\Order\Contract\OrderStatusServiceInterface;

/**
 * The order settings show these states in three dropdowns. Changing a state
 * follows in INT-1962.
 */
final class OrderStatusService implements OrderStatusServiceInterface
{
    public function __construct(private readonly OrderStatusProviderInterface $orderStatusProvider)
    {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->orderStatusProvider->all();
    }

    /**
     * @param  array<int|string> $orderIds
     */
    public function updateStatus(array $orderIds, string $status): void
    {
        throw NotImplementedException::forContract(OrderStatusServiceInterface::class);
    }
}
