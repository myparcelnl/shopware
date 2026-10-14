<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order;

interface OrderStatusProviderInterface
{
    /**
     * Lists the states of the order state machine.
     *
     * @return array<string, string> technical name => translated name
     */
    public function all(): array;
}
