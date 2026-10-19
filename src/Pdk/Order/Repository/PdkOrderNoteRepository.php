<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Repository;

use MyParcel\Shopware\Pdk\Order\Contract\OrderStorageInterface;
use MyParcelNL\Pdk\App\Order\Collection\PdkOrderNoteCollection;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\App\Order\Model\PdkOrderNote;
use MyParcelNL\Pdk\App\Order\Repository\AbstractPdkOrderNoteRepository;
use MyParcelNL\Pdk\Facade\Logger;
use MyParcelNL\Pdk\Fulfilment\Model\OrderNote;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;

/**
 * Order notes for the PDK. Shopware has no order notes, so the PDK's notes live in
 * the notes column of myparcel_order_data. The customer's order comment is shown as
 * a customer note, the way WooCommerce shows its customer note, and is never stored.
 */
final class PdkOrderNoteRepository extends AbstractPdkOrderNoteRepository
{
    public const CUSTOMER_NOTE_IDENTIFIER = 'customer_comment';

    public function __construct(StorageInterface $storage, private readonly OrderStorageInterface $orderStorage)
    {
        parent::__construct($storage);
    }

    /**
     * Returns the notes of an order: the customer comment first, when there is one,
     * then the stored notes. Use it to show all notes of an order.
     */
    public function getFromOrder(PdkOrder $order): PdkOrderNoteCollection
    {
        $orderId = (string) $order->externalIdentifier;
        $stored  = $this->orderStorage->getNotes($orderId);
        $notes   = array_map(static fn (array $note) => new PdkOrderNote($note), $stored['notes']);
        $comment = trim((string) ($stored['customerComment'] ?? ''));

        if ('' !== $comment) {
            array_unshift($notes, new PdkOrderNote([
                'externalIdentifier' => self::CUSTOMER_NOTE_IDENTIFIER,
                'orderIdentifier'    => $orderId,
                'author'             => OrderNote::AUTHOR_CUSTOMER,
                'note'               => $comment,
            ]));
        }

        return new PdkOrderNoteCollection($notes);
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

        $notes    = $this->orderStorage->getNotes($orderId)['notes'];
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

        $this->orderStorage->saveNotes($orderId, array_values($notes));
    }
}
