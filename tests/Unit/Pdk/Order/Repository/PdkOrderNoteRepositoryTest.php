<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderNoteRepository;
use MyParcel\Shopware\Tests\Support\InMemoryOrderStorage;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\App\Order\Model\PdkOrderNote;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;

beforeEach(fn () => TestPdk::boot());

function noteRepository(InMemoryOrderStorage $storage): PdkOrderNoteRepository
{
    return new PdkOrderNoteRepository(new MemoryCacheStorage(), $storage);
}

it('adds the customer comment as the first, customer note', function () {
    $storage                           = new InMemoryOrderStorage();
    $storage->customerComments['o1']   = 'Graag bij de buren afgeven';
    $storage->notes['o1']              = [['externalIdentifier' => 'n1', 'orderIdentifier' => 'o1', 'author' => 'webshop', 'note' => 'Ingepakt']];

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

it('appends a new note and replaces one with the same identifier', function () {
    $storage    = new InMemoryOrderStorage();
    $repository = noteRepository($storage);

    $repository->add(new PdkOrderNote(['externalIdentifier' => 'n1', 'orderIdentifier' => 'o1', 'author' => 'webshop', 'note' => 'Eerste']));
    $repository->add(new PdkOrderNote(['externalIdentifier' => 'n2', 'orderIdentifier' => 'o1', 'author' => 'webshop', 'note' => 'Tweede']));
    $repository->update(new PdkOrderNote(['externalIdentifier' => 'n1', 'orderIdentifier' => 'o1', 'author' => 'webshop', 'note' => 'Eerste, gewijzigd']));

    expect(array_column($storage->notes['o1'], 'note'))->toBe(['Eerste, gewijzigd', 'Tweede']);
});

it('appends a note without an identifier and never replaces another note with it', function () {
    $storage    = new InMemoryOrderStorage();
    $repository = noteRepository($storage);

    $repository->add(new PdkOrderNote(['externalIdentifier' => 'n1', 'orderIdentifier' => 'o1', 'note' => 'Met id']));
    $repository->add(new PdkOrderNote(['orderIdentifier' => 'o1', 'note' => 'Zonder id']));
    $repository->add(new PdkOrderNote(['orderIdentifier' => 'o1', 'note' => 'Nog een zonder id']));

    expect(array_column($storage->notes['o1'], 'note'))->toBe(['Met id', 'Zonder id', 'Nog een zonder id']);
});

it('never stores the customer note', function () {
    $storage                         = new InMemoryOrderStorage();
    $storage->customerComments['o1'] = 'Graag bij de buren afgeven';
    $repository                      = noteRepository($storage);

    $repository->updateMany($repository->getFromOrder(new PdkOrder(['externalIdentifier' => 'o1'])));

    expect($storage->notes)->toBe([]);
});

it('skips a note without an order and logs it', function () {
    $storage = new InMemoryOrderStorage();

    noteRepository($storage)->add(new PdkOrderNote(['externalIdentifier' => 'n1', 'note' => 'Los']));

    expect($storage->notes)->toBe([])
        ->and(TestPdk::logs('warning'))->toHaveCount(1);
});
