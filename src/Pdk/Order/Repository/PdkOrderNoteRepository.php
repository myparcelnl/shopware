<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Repository;

use MyParcel\Shopware\Entity\OrderData\OrderDataEntity;
use MyParcel\Shopware\Entity\OrderExtension;
use MyParcel\Shopware\Pdk\Order\Mapper\OrderNotesBuilder;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcelNL\Pdk\App\Order\Collection\PdkOrderNoteCollection;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\App\Order\Model\PdkOrderNote;
use MyParcelNL\Pdk\App\Order\Repository\AbstractPdkOrderNoteRepository;
use MyParcelNL\Pdk\Facade\Logger;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Order notes for the PDK. Shopware has no order notes, so the PDK's notes live in
 * the notes column of myparcel_order_data. The customer's order comment is shown as
 * a customer note, the way WooCommerce shows its customer note, and is never stored.
 *
 * Reads run in the system scope, like those of PdkOrderRepository.
 */
final class PdkOrderNoteRepository extends AbstractPdkOrderNoteRepository
{
    public const CUSTOMER_NOTE_IDENTIFIER = OrderNotesBuilder::CUSTOMER_NOTE_IDENTIFIER;

    /**
     * $storage is the PDK's in-request cache, not a Shopware store.
     *
     * @param  EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        StorageInterface $storage,
        private readonly EntityRepository $orderRepository,
        private readonly OrderDataWriter $orderDataWriter
    ) {
        parent::__construct($storage);
    }

    /**
     * Returns the notes of an order: the customer comment first, when there is one,
     * then the stored notes. The PDK also calls it from the barcode note flow
     * (ShipmentUpdateService::addBarcodeNotes()) for an order that already has
     * notes, so it builds them with OrderNotesBuilder, the same as PdkOrderMapper.
     */
    public function getFromOrder(PdkOrder $order): PdkOrderNoteCollection
    {
        $orderId = (string) $order->externalIdentifier;
        $stored  = $this->readNotes($orderId);

        return OrderNotesBuilder::build($orderId, $stored['notes'], $stored['customerComment']);
    }

    /**
     * Stores a new note on its order. Use it when the PDK creates a note.
     */
    public function add(PdkOrderNote $note): void
    {
        $this->update($note);
    }

    /**
     * Replaces the stored note with the same externalIdentifier, or appends it. A
     * note without an identifier is always appended. Use it when the PDK changes a note.
     */
    public function update(PdkOrderNote $note): void
    {
        if (self::CUSTOMER_NOTE_IDENTIFIER === $note->externalIdentifier) {
            return;
        }

        $orderId = (string) $note->orderIdentifier;

        if ('' === $orderId) {
            Logger::warning('MyParcel: an order note without an order was not stored', ['externalIdentifier' => $note->externalIdentifier]);

            return;
        }

        $notes    = $this->readNotes($orderId)['notes'];
        $replaced = false;

        foreach ($notes as $index => $stored) {
            if (null !== $note->externalIdentifier && ($stored['externalIdentifier'] ?? null) === $note->externalIdentifier) {
                $notes[$index] = $note->toStorableArray();
                $replaced      = true;
            }
        }

        if (! $replaced) {
            $notes[] = $note->toStorableArray();
        }

        $this->orderDataWriter->saveNotes($orderId, array_values($notes));
    }

    /**
     * The stored notes of the order and its customer comment. The plugin never
     * stores the customer comment.
     *
     * @return array{notes: list<array<string, mixed>>, customerComment: ?string}
     */
    private function readNotes(string $orderId): array
    {
        if (! Uuid::isValid($orderId)) {
            return ['notes' => [], 'customerComment' => null];
        }

        $criteria = (new Criteria([$orderId]))->addAssociation(OrderExtension::ORDER_DATA);

        /** @var null|OrderEntity $order */
        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->first();

        /** @var null|OrderDataEntity $orderData */
        $orderData = $order?->getExtension(OrderExtension::ORDER_DATA);

        return ['notes' => $orderData?->getNotes() ?? [], 'customerComment' => $order?->getCustomerComment()];
    }
}
