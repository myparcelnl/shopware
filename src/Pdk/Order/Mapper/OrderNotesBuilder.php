<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Mapper;

use MyParcelNL\Pdk\App\Order\Collection\PdkOrderNoteCollection;
use MyParcelNL\Pdk\App\Order\Model\PdkOrderNote;
use MyParcelNL\Pdk\Fulfilment\Model\OrderNote;

/**
 * Builds the notes of an order from the stored PDK notes and the customer's order
 * comment. The customer comment is shown as the first note, a customer note, the
 * way WooCommerce shows its customer note. It is never stored.
 */
final class OrderNotesBuilder
{
    public const CUSTOMER_NOTE_IDENTIFIER = 'customer_comment';

    /**
     * Builds the note collection of one order. Use it wherever a PdkOrder gets its
     * notes, so the mapper and the note repository give the same notes.
     *
     * @param  list<array<string, mixed>> $storedNotes PdkOrderNote::toStorableArray() per note
     */
    public static function build(string $orderId, array $storedNotes, ?string $customerComment): PdkOrderNoteCollection
    {
        $notes   = array_map(static fn (array $note) => new PdkOrderNote($note), $storedNotes);
        $comment = trim((string) $customerComment);

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
}
