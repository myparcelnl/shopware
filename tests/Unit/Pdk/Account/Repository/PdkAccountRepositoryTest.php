<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Account\Repository\PdkAccountRepository;
use MyParcel\Shopware\Tests\Support\InMemoryConfigStorage;
use MyParcelNL\Pdk\Account\Model\Account;
use MyParcelNL\Pdk\Account\Repository\AccountRepository;
use MyParcelNL\Pdk\App\Account\Repository\AbstractPdkAccountRepository;
use MyParcelNL\Pdk\Settings\Contract\PdkSettingsRepositoryInterface;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use PHPUnit\Framework\TestCase;

/**
 * The API-backed collaborators are never called: an account in storage is
 * returned without asking the API. getMockBuilder() because it is public,
 * unlike createStub(), so this helper can call it from outside the test.
 */
function accountRepository(TestCase $test, InMemoryConfigStorage $config): PdkAccountRepository
{
    return new PdkAccountRepository(
        new MemoryCacheStorage(),
        $test->getMockBuilder(AccountRepository::class)->disableOriginalConstructor()->getMock(),
        $test->getMockBuilder(PdkSettingsRepositoryInterface::class)->getMock(),
        $config
    );
}

it('is a pdk account repository', function () {
    expect(accountRepository($this, new InMemoryConfigStorage()))->toBeInstanceOf(AbstractPdkAccountRepository::class);
});

it('persists the storable form of an account', function () {
    $config  = new InMemoryConfigStorage();
    $account = new Account(['id' => 3, 'platformId' => 1]);

    $stored = accountRepository($this, $config)->store($account);

    expect($stored)->toBe($account)
        ->and($config->values['account'])->toBe($account->toStorableArray());
});

it('reads a stored account back in a new request', function () {
    $config = new InMemoryConfigStorage();
    accountRepository($this, $config)->store(new Account(['id' => 3, 'platformId' => 2]));

    $account = accountRepository($this, $config)->getAccount();

    expect($account)->toBeInstanceOf(Account::class)
        ->and($account->id)->toBe(3)
        ->and($account->platformId)->toBe(2);
});

it('deletes the stored account when given null', function () {
    $config = new InMemoryConfigStorage();
    accountRepository($this, $config)->store(new Account(['id' => 3, 'platformId' => 1]));

    expect(accountRepository($this, $config)->store(null))->toBeNull()
        ->and($config->values)->not->toHaveKey('account');
});

it('has no account in storage when nothing was stored', function () {
    $repository = accountRepository($this, new InMemoryConfigStorage());

    expect((new ReflectionMethod($repository, 'getFromStorage'))->invoke($repository))->toBeNull();
});
