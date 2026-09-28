<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Settings\Repository;

use MyParcel\Shopware\Pdk\Storage\ConfigStorageInterface;
use MyParcelNL\Pdk\Settings\Repository\AbstractPdkSettingsRepository;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;

/**
 * Persists PDK settings one group at a time, the way the PDK hands them over.
 *
 * Settings are installation-wide: the PDK knows one scope, and with one
 * account per installation the account settings are global anyway.
 */
final class PdkSettingsRepository extends AbstractPdkSettingsRepository
{
    public function __construct(StorageInterface $storage, private readonly ConfigStorageInterface $config)
    {
        parent::__construct($storage);
    }

    /**
     * @param  string $namespace
     *
     * @return mixed
     */
    public function getGroup(string $namespace)
    {
        return $this->retrieve($namespace, fn () => $this->config->get($namespace));
    }

    /**
     * @param  string $key
     * @param  mixed  $value
     */
    public function store(string $key, $value): void
    {
        $this->config->set($key, $value);

        $this->save($key, $value);
    }
}
