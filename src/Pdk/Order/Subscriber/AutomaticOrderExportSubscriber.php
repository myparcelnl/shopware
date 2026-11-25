<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Subscriber;

use MyParcel\Shopware\Pdk\Order\AutomaticOrderExport;
use MyParcel\Shopware\Pdk\PdkInitializer;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

/**
 * Starts the automatic export when an order reaches the state the merchant set
 * for it: a new order from the checkout, and every later state change, in the
 * admin or through the PDK itself. The transitions of order deliveries and
 * order transactions are left out.
 *
 * A failed export does not stop the order: the merchant sees the order without
 * a shipment and can export it by hand.
 *
 * @TODO INT-2151: cover the order path with an integration test in a Shopware installation.
 */
final class AutomaticOrderExportSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly AutomaticOrderExport $automaticOrderExport,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onTransition',
            CheckoutOrderPlacedEvent::class    => 'onOrderPlaced',
        ];
    }

    /**
     * Passes the order and its new state to the automatic export. Shopware
     * calls it after each state machine transition.
     */
    public function onTransition(StateMachineTransitionEvent $event): void
    {
        if (OrderDefinition::ENTITY_NAME !== $event->getEntityName()) {
            return;
        }

        $this->export($event->getEntityId(), $event->getToPlace()->getTechnicalName());
    }

    /**
     * Passes a new order and its first state to the automatic export. The
     * checkout creates the order through the data abstraction layer, without a
     * transition, so onTransition() never sees it.
     */
    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $state = $event->getOrder()
            ->getStateMachineState()
            ?->getTechnicalName();

        if (null !== $state) {
            $this->export($event->getOrderId(), $state);
        }
    }

    /**
     * Boots the PDK and hands the order to the export. A failure goes no
     * further than the log.
     */
    private function export(string $orderId, string $state): void
    {
        try {
            $this->pdkInitializer->boot();

            $this->automaticOrderExport->onOrderState($orderId, $state);
        } catch (Throwable $exception) {
            $this->logger->error('MyParcel: the automatic export of an order failed', [
                'orderId'   => $orderId,
                'exception' => $exception,
            ]);
        }
    }
}
