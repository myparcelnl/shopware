<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Account\Repository;

use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use MyParcelNL\Pdk\Account\Model\Account;
use MyParcelNL\Pdk\Account\Repository\AccountRepository;
use MyParcelNL\Pdk\App\Account\Repository\AbstractPdkAccountRepository;
use MyParcelNL\Pdk\Settings\Contract\PdkSettingsRepositoryInterface;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;

/**
 * Keeps the account that belongs to the API key, so the plugin does not fetch
 * it from the API on every request.
 *
 * Validating the key and choosing the proposition are the PDK's job, see
 * UpdateAccountAction; this class only stores what it is given.
 */
final class PdkAccountRepository extends AbstractPdkAccountRepository
{
    /**
     * Not "account": the PDK stores the account settings, API key included, under
     * that key. The 'account' passed to save() is the PDK's in-memory cache key,
     * which AbstractPdkAccountRepository reads back by that literal name.
     */
    private const STORAGE_KEY = 'account_data';

    public function __construct(
        StorageInterface $storage,
        AccountRepository $accountRepository,
        PdkSettingsRepositoryInterface $settingsRepository,
        private readonly ConfigStorageInterface $config
    ) {
        parent::__construct($storage, $accountRepository, $settingsRepository);
    }

    public function store(?Account $account): ?Account
    {
        $this->save('account', $account);

        if (null === $account) {
            $this->config->delete(self::STORAGE_KEY);

            return null;
        }

        $this->config->set(self::STORAGE_KEY, $account->toStorableArray());

        return $account;
    }

    protected function getFromStorage(): ?Account
    {
        $data = $this->config->get(self::STORAGE_KEY);

        return is_array($data) ? new Account($data) : null;
    }
}
