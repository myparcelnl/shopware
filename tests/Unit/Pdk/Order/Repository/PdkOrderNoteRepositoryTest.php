<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderNoteRepository;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\RecordingConnection;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\App\Order\Model\PdkOrderNote;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;

beforeEach(fn () => TestPdk::boot());

/**
 * A note repository on one order with these stored notes. The upserts of the
 * order data go to $orderData.
 *
 * @param  list<array<string, mixed>> $notes
 */
function noteRepository(array $notes, ?FakeEntityRepository $orderData = null, ?string $customerComment = null): PdkOrderNoteRepository
{
    return new PdkOrderNoteRepository(
        new MemoryCacheStorage(),
        FakeEntityRepository::with(Entities::order(['notes' => $notes, 'customerComment' => $customerComment])),
        new OrderDataWriter($orderData ?? FakeEntityRepository::with(), FakeEntityRepository::with(), new RecordingConnection())
    );
}

/**
 * @return list<string>
 */
function savedNoteTexts(FakeEntityRepository $orderData): array
{
    return array_column($orderData->upserts[0]['notes'], 'note');
}

it('adds the customer comment as the first, customer note', function () {
    $notes = noteRepository(
        [['externalIdentifier' => 'n1', 'orderIdentifier' => Entities::ORDER_ID, 'author' => 'webshop', 'note' => 'Ingepakt']],
        null,
        'Graag bij de buren afgeven'
    )->getFromOrder(new PdkOrder(['externalIdentifier' => Entities::ORDER_ID]));

    expect($notes)->toHaveCount(2)
        ->and($notes->first()->toArray())->toMatchArray([
            'externalIdentifier' => 'customer_comment',
            'orderIdentifier'    => Entities::ORDER_ID,
            'author'             => 'customer',
            'note'               => 'Graag bij de buren afgeven',
        ])
        ->and($notes->last()->note)->toBe('Ingepakt');
});

it('returns no notes for an order without a comment or stored notes', function () {
    expect(noteRepository([])->getFromOrder(new PdkOrder(['externalIdentifier' => Entities::ORDER_ID])))->toHaveCount(0)
        ->and(noteRepository([])->getFromOrder(new PdkOrder(['externalIdentifier' => 'not-an-order'])))->toHaveCount(0);
});

it('appends a new note', function () {
    $orderData = FakeEntityRepository::with();

    noteRepository([['externalIdentifier' => 'n1', 'orderIdentifier' => Entities::ORDER_ID, 'note' => 'Eerste']], $orderData)
        ->add(new PdkOrderNote(['externalIdentifier' => 'n2', 'orderIdentifier' => Entities::ORDER_ID, 'author' => 'webshop', 'note' => 'Tweede']));

    expect(savedNoteTexts($orderData))->toBe(['Eerste', 'Tweede']);
});

it('replaces the stored note with the same identifier', function () {
    $orderData = FakeEntityRepository::with();

    noteRepository([
        ['externalIdentifier' => 'n1', 'orderIdentifier' => Entities::ORDER_ID, 'note' => 'Eerste'],
        ['externalIdentifier' => 'n2', 'orderIdentifier' => Entities::ORDER_ID, 'note' => 'Tweede'],
    ], $orderData)->update(new PdkOrderNote(['externalIdentifier' => 'n1', 'orderIdentifier' => Entities::ORDER_ID, 'author' => 'webshop', 'note' => 'Eerste, gewijzigd']));

    expect(savedNoteTexts($orderData))->toBe(['Eerste, gewijzigd', 'Tweede']);
});

it('appends a note without an identifier and never replaces another note with it', function () {
    $orderData = FakeEntityRepository::with();

    noteRepository([
        ['externalIdentifier' => 'n1', 'orderIdentifier' => Entities::ORDER_ID, 'note' => 'Met id'],
        ['orderIdentifier' => Entities::ORDER_ID, 'note' => 'Zonder id'],
    ], $orderData)->add(new PdkOrderNote(['orderIdentifier' => Entities::ORDER_ID, 'note' => 'Nog een zonder id']));

    expect(savedNoteTexts($orderData))->toBe(['Met id', 'Zonder id', 'Nog een zonder id']);
});

it('never stores the customer note', function () {
    $orderData  = FakeEntityRepository::with();
    $repository = noteRepository([], $orderData, 'Graag bij de buren afgeven');

    $repository->updateMany($repository->getFromOrder(new PdkOrder(['externalIdentifier' => Entities::ORDER_ID])));

    expect($orderData->upserts)->toBe([]);
});

it('skips a note without an order and logs it', function () {
    $orderData = FakeEntityRepository::with();

    noteRepository([], $orderData)->add(new PdkOrderNote(['externalIdentifier' => 'n1', 'note' => 'Los']));

    expect($orderData->upserts)->toBe([])
        ->and(TestPdk::logs('warning'))->toHaveCount(1);
});
