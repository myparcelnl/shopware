<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\ShippingMethod\Repository\PdkShippingMethodRepository;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\ShippingMethod\Contract\PdkShippingMethodRepositoryInterface;

beforeEach(fn () => TestPdk::boot());

it('is a pdk shipping method repository', function () {
    expect(new PdkShippingMethodRepository(FakeEntityRepository::with()))
        ->toBeInstanceOf(PdkShippingMethodRepositoryInterface::class);
});

it('lists every shopware shipping method by id, with its translated name', function () {
    $repository = FakeEntityRepository::with(
        Checkout::shippingMethod(),
        Checkout::shippingMethod('0190a1b2c3d4e5f60718293a4b5c5002', 'Mailbox', false)
    );

    $methods = (new PdkShippingMethodRepository($repository))->all();

    expect($methods->pluck('id')->all())->toBe([Checkout::SHIPPING_METHOD_ID, '0190a1b2c3d4e5f60718293a4b5c5002'])
        ->and($methods->pluck('name')->all())->toBe(['Standard', 'Mailbox'])
        ->and($methods->pluck('description')->all())->toBe(['Standard delivery', 'Mailbox delivery'])
        ->and($methods->pluck('isEnabled')->all())->toBe([true, false]);
});

it('sorts the methods as the shopware admin does', function () {
    $repository = FakeEntityRepository::with();

    (new PdkShippingMethodRepository($repository))->all();

    expect(array_map(static fn ($sorting) => $sorting->getField(), $repository->criteria[0]->getSorting()))
        ->toBe(['position', 'name']);
});
