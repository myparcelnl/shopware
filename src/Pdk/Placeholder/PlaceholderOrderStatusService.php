<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Placeholder;

use MyParcelNL\Pdk\App\Order\Contract\OrderStatusServiceInterface;

/**
 * Temporary. It exists only so that the PDK container compiles in production
 * mode.
 *
 * @TODO INT-1958: replace with the real order status service.
 */
final class PlaceholderOrderStatusService implements OrderStatusServiceInterface
{
    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        throw NotImplementedException::forContract(OrderStatusServiceInterface::class);
    }

    /**
     * @param  array<int|string> $orderIds
     */
    public function updateStatus(array $orderIds, string $status): void
    {
        throw NotImplementedException::forContract(OrderStatusServiceInterface::class);
    }
}
