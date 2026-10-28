<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\State;

use MyParcel\Shopware\Pdk\Order\Contract\OrderStateTransitionerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Throwable;

/**
 * Moves orders to a target state of order.state with one direct transition, the
 * way WooCommerce sets an order status directly. It does not walk intermediate
 * states: each of those would run its own flows and mails. An order without a
 * direct transition to the target, or whose transition fails, is logged and
 * skipped; the other orders go on.
 */
final class OrderStateTransitioner implements OrderStateTransitionerInterface
{
    /**
     * @param  EntityRepository<OrderCollection>                  $orderRepository
     * @param  EntityRepository<StateMachineTransitionCollection> $transitionRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $transitionRepository,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Runs, for each order, the one transition from its current state to the
     * target state. Use it when the PDK changes an order status.
     */
    public function transitionTo(array $orderIds, string $technicalName): void
    {
        $ids = array_values(array_filter($orderIds, [Uuid::class, 'isValid']));

        if ([] === $ids) {
            return;
        }

        $context     = Context::createDefaultContext();
        $transitions = $this->loadTransitions($context);
        $orders      = $this->orderRepository
            ->search((new Criteria($ids))->addAssociation('stateMachineState'), $context)
            ->getEntities();

        /** @var OrderEntity $order */
        foreach ($orders as $order) {
            $current = $order->getStateMachineState()?->getTechnicalName();

            if ($technicalName === $current) {
                continue;
            }

            $action = $transitions[$current . '>' . $technicalName] ?? null;

            if (null === $action) {
                $this->logger->warning('MyParcel: no direct transition to the order state', [
                    'orderId' => $order->getId(),
                    'from'    => $current,
                    'to'      => $technicalName,
                ]);

                continue;
            }

            try {
                $this->stateMachineRegistry->transition(
                    new Transition(OrderDefinition::ENTITY_NAME, $order->getId(), $action, 'stateId'),
                    $context
                );
            } catch (Throwable $exception) {
                $this->logger->error('MyParcel: an order state transition failed', [
                    'orderId'   => $order->getId(),
                    'action'    => $action,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * The action of every transition of order.state, keyed by "from>to".
     *
     * @return array<string, string>
     */
    private function loadTransitions(Context $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('stateMachine.technicalName', OrderStates::STATE_MACHINE))
            ->addAssociation('fromStateMachineState')
            ->addAssociation('toStateMachineState');

        $transitions = [];

        /** @var StateMachineTransitionEntity $transition */
        foreach ($this->transitionRepository->search($criteria, $context)->getEntities() as $transition) {
            $from = $transition->getFromStateMachineState()?->getTechnicalName();
            $to   = $transition->getToStateMachineState()?->getTechnicalName();

            if (null !== $from && null !== $to) {
                $transitions[$from . '>' . $to] = $transition->getActionName();
            }
        }

        return $transitions;
    }
}
