<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Tests\Support\Checkout;

it('gives the context that was set', function () {
    $holder  = new SalesChannelContextHolder();
    $context = Checkout::context();

    $holder->set($context);

    expect($holder->has())->toBeTrue()
        ->and($holder->get())->toBe($context);
});

it('throws when no context was set', function () {
    expect((new SalesChannelContextHolder())->has())->toBeFalse()
        ->and(fn () => (new SalesChannelContextHolder())->get())->toThrow(LogicException::class);
});

it('forgets the context on reset, so the next request does not see it', function () {
    $holder = new SalesChannelContextHolder();
    $holder->set(Checkout::context());

    $holder->reset();

    expect($holder->has())->toBeFalse();
});
