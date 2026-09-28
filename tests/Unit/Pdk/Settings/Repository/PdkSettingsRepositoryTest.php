<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Settings\Repository\PdkSettingsRepository;
use MyParcel\Shopware\Tests\Support\InMemoryConfigStorage;
use MyParcelNL\Pdk\Settings\Repository\AbstractPdkSettingsRepository;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;

function settingsRepository(InMemoryConfigStorage $config): PdkSettingsRepository
{
    return new PdkSettingsRepository(new MemoryCacheStorage(), $config);
}

it('is a pdk settings repository', function () {
    expect(settingsRepository(new InMemoryConfigStorage()))->toBeInstanceOf(AbstractPdkSettingsRepository::class);
});

it('returns null for a group that was never stored', function () {
    expect(settingsRepository(new InMemoryConfigStorage())->getGroup('myparcel_account'))->toBeNull();
});

it('persists a group under its own key', function () {
    $config = new InMemoryConfigStorage();

    settingsRepository($config)->store('myparcel_account', ['apiKeyValid' => true]);

    expect($config->values)->toBe(['myparcel_account' => ['apiKeyValid' => true]]);
});

it('reads a stored group back with its types in a new request', function () {
    $config = new InMemoryConfigStorage();
    $group  = ['enabled' => true, 'count' => 3, 'name' => 'MyParcel', 'nested' => ['off' => false]];

    settingsRepository($config)->store('myparcel_general', $group);

    expect(settingsRepository($config)->getGroup('myparcel_general'))->toBe($group);
});

it('returns the new value after a store in the same request', function () {
    $repository = settingsRepository(new InMemoryConfigStorage());

    $repository->getGroup('myparcel_general');
    $repository->store('myparcel_general', ['enabled' => false]);

    expect($repository->getGroup('myparcel_general'))->toBe(['enabled' => false]);
});
