<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Contract;

/**
 * Moves orders to a state of Shopware's order.state machine through a transition,
 * so Shopware's events, flows and mails run as usual.
 */
interface OrderStateTransitionerInterface
{
    /**
     * Moves each order to this state with one direct transition. An order that is
     * in the state already is left alone; an order without a direct transition to
     * it is logged and skipped. Use it when the PDK changes an order status
     * (OrderStatusServiceInterface::updateStatus).
     *
     * @param  list<string> $orderIds
     * @param  string       $technicalName the target state, as OrderStatusServiceInterface::all() lists it
     */
    public function transitionTo(array $orderIds, string $technicalName): void;
}
