<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Subscriber;

use MyParcel\Shopware\Pdk\Order\AutomaticOrderExport;
use MyParcel\Shopware\Pdk\PdkInitializer;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

/**
 * Starts the automatic export after an order changes state, in the admin, in
 * the checkout or through the PDK itself. The transitions of order deliveries
 * and order transactions are left out.
 *
 * A failed export does not stop the state change: the merchant sees the order
 * without a shipment and can export it by hand.
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
        return [StateMachineTransitionEvent::class => 'onTransition'];
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

        try {
            $this->pdkInitializer->boot();

            $this->automaticOrderExport->onOrderState($event->getEntityId(), $event->getToPlace()->getTechnicalName());
        } catch (Throwable $exception) {
            $this->logger->error('MyParcel: the automatic export of an order failed', [
                'orderId'   => $event->getEntityId(),
                'exception' => $exception,
            ]);
        }
    }
}
