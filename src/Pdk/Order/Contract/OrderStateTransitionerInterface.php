<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Contract;

/**
 * Moves orders to a state of Shopware's order.state machine through its
 * transitions, so Shopware's events, flows and mails run as usual.
 */
interface OrderStateTransitionerInterface
{
    /**
     * Moves each order to this state along the shortest transition path; orders that
     * cannot reach it are logged and skipped. Use it when the PDK changes an order
     * status (OrderStatusServiceInterface::updateStatus).
     *
     * @param  list<string> $orderIds
     * @param  string       $technicalName the target state, as OrderStatusServiceInterface::all() lists it
     */
    public function transitionTo(array $orderIds, string $technicalName): void;
}
