<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Placeholder\NotImplementedException;
use MyParcel\Shopware\Pdk\Placeholder\PlaceholderCartRepository;
use MyParcel\Shopware\Pdk\Placeholder\PlaceholderOrderRepository;
use MyParcel\Shopware\Pdk\Placeholder\PlaceholderShippingMethodRepository;
use MyParcelNL\Pdk\App\Cart\Contract\PdkCartRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\App\ShippingMethod\Collection\PdkShippingMethodCollection;

dataset('placeholders', [
    'order repository'           => [PlaceholderOrderRepository::class, PdkOrderRepositoryInterface::class, 'find', [1]],
    'cart repository'            => [PlaceholderCartRepository::class, PdkCartRepositoryInterface::class, 'get', [null]],
]);

it('implements its pdk contract', function (string $placeholder, string $contract) {
    expect(new $placeholder())->toBeInstanceOf($contract);
})->with('placeholders');

it('throws when a method is called, naming the contract', function (
    string $placeholder,
    string $contract,
    string $method,
    array $arguments
) {
    $shortName = (new ReflectionClass($contract))->getShortName();

    expect(fn () => (new $placeholder())->{$method}(...$arguments))
        ->toThrow(NotImplementedException::class, $shortName);
})->with('placeholders');

it('builds the message from the short contract name', function () {
    $exception = NotImplementedException::forContract(PdkOrderRepositoryInterface::class);

    expect($exception)
        ->toBeInstanceOf(LogicException::class)
        ->and($exception->getMessage())
        ->toBe('PdkOrderRepositoryInterface is not implemented yet in the Shopware plugin.');
});

it('lists no shipping methods until the checkout ticket', function () {
    $methods = (new PlaceholderShippingMethodRepository())->all();

    expect($methods)->toBeInstanceOf(PdkShippingMethodCollection::class)
        ->and($methods->count())->toBe(0);
});
