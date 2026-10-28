<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderNoteRepository;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\InMemoryOrderStorage;
use MyParcel\Shopware\Tests\Support\RecordingConnection;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\App\Order\Model\PdkOrderNote;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;

beforeEach(fn () => TestPdk::boot());

/**
 * A note repository on this storage. The upserts of the order data go to $orderData.
 */
function noteRepository(InMemoryOrderStorage $storage, ?FakeEntityRepository $orderData = null): PdkOrderNoteRepository
{
    return new PdkOrderNoteRepository(
        new MemoryCacheStorage(),
        $storage,
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
    $storage                         = new InMemoryOrderStorage();
    $storage->customerComments['o1'] = 'Graag bij de buren afgeven';
    $storage->notes['o1']            = [['externalIdentifier' => 'n1', 'orderIdentifier' => 'o1', 'author' => 'webshop', 'note' => 'Ingepakt']];

    $notes = noteRepository($storage)->getFromOrder(new PdkOrder(['externalIdentifier' => 'o1']));

    expect($notes)->toHaveCount(2)
        ->and($notes->first()->toArray())->toMatchArray([
            'externalIdentifier' => 'customer_comment',
            'orderIdentifier'    => 'o1',
            'author'             => 'customer',
            'note'               => 'Graag bij de buren afgeven',
        ])
        ->and($notes->last()->note)->toBe('Ingepakt');
});

it('returns no notes for an order without a comment or stored notes', function () {
    expect(noteRepository(new InMemoryOrderStorage())->getFromOrder(new PdkOrder(['externalIdentifier' => 'o1'])))->toHaveCount(0);
});

it('appends a new note', function () {
    $orderData            = FakeEntityRepository::with();
    $storage              = new InMemoryOrderStorage();
    $storage->notes['o1'] = [['externalIdentifier' => 'n1', 'orderIdentifier' => 'o1', 'note' => 'Eerste']];

    noteRepository($storage, $orderData)
        ->add(new PdkOrderNote(['externalIdentifier' => 'n2', 'orderIdentifier' => 'o1', 'author' => 'webshop', 'note' => 'Tweede']));

    expect(savedNoteTexts($orderData))->toBe(['Eerste', 'Tweede']);
});

it('replaces the stored note with the same identifier', function () {
    $orderData            = FakeEntityRepository::with();
    $storage              = new InMemoryOrderStorage();
    $storage->notes['o1'] = [
        ['externalIdentifier' => 'n1', 'orderIdentifier' => 'o1', 'note' => 'Eerste'],
        ['externalIdentifier' => 'n2', 'orderIdentifier' => 'o1', 'note' => 'Tweede'],
    ];

    noteRepository($storage, $orderData)
        ->update(new PdkOrderNote(['externalIdentifier' => 'n1', 'orderIdentifier' => 'o1', 'author' => 'webshop', 'note' => 'Eerste, gewijzigd']));

    expect(savedNoteTexts($orderData))->toBe(['Eerste, gewijzigd', 'Tweede']);
});

it('appends a note without an identifier and never replaces another note with it', function () {
    $orderData            = FakeEntityRepository::with();
    $storage              = new InMemoryOrderStorage();
    $storage->notes['o1'] = [
        ['externalIdentifier' => 'n1', 'orderIdentifier' => 'o1', 'note' => 'Met id'],
        ['orderIdentifier' => 'o1', 'note' => 'Zonder id'],
    ];

    noteRepository($storage, $orderData)->add(new PdkOrderNote(['orderIdentifier' => 'o1', 'note' => 'Nog een zonder id']));

    expect(savedNoteTexts($orderData))->toBe(['Met id', 'Zonder id', 'Nog een zonder id']);
});

it('never stores the customer note', function () {
    $orderData                       = FakeEntityRepository::with();
    $storage                         = new InMemoryOrderStorage();
    $storage->customerComments['o1'] = 'Graag bij de buren afgeven';
    $repository                      = noteRepository($storage, $orderData);

    $repository->updateMany($repository->getFromOrder(new PdkOrder(['externalIdentifier' => 'o1'])));

    expect($orderData->upserts)->toBe([]);
});

it('skips a note without an order and logs it', function () {
    $orderData = FakeEntityRepository::with();

    noteRepository(new InMemoryOrderStorage(), $orderData)->add(new PdkOrderNote(['externalIdentifier' => 'n1', 'note' => 'Los']));

    expect($orderData->upserts)->toBe([])
        ->and(TestPdk::logs('warning'))->toHaveCount(1);
});
