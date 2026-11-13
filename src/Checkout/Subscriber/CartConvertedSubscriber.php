<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout\Subscriber;

use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Entity\OrderExtension;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes the chosen delivery options to the order data when Shopware turns the
 * cart into an order, in the same write as the order. PdkOrderMapper reads
 * them from there, so the export has the consumer's choice.
 *
 * The shipping address of the order stays the consumer's address, also for a
 * pickup point: the pickup location is part of the delivery options, and the
 * PDK puts it on the label.
 */
final class CartConvertedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [CartConvertedEvent::class => 'onCartConverted'];
    }

    /**
     * Adds the order data with the delivery options to the order that Shopware
     * is about to write. A cart without a choice gives no order data.
     */
    public function onCartConverted(CartConvertedEvent $event): void
    {
        $deliveryOptions = CartDeliveryOptions::read($event->getCart());

        if (null === $deliveryOptions) {
            return;
        }

        $converted = $event->getConvertedCart();

        $converted[OrderExtension::ORDER_DATA] = [
            'id'   => OrderDataWriter::buildOrderDataId($converted['id']),
            'data' => ['deliveryOptions' => $deliveryOptions],
        ];

        $event->setConvertedCart($converted);
    }
}
