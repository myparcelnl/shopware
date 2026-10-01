<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Storage;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Stores PDK values in Shopware's system_config, without a sales channel.
 *
 * system_config keeps each value as JSON ({"_value": ...}), so booleans,
 * integers, strings and arrays come back with the type they went in with.
 */
final class SystemConfigStorage implements ConfigStorageInterface
{
    public const KEY_PREFIX = 'MyParcelShopware.pdk.';

    public function __construct(private readonly SystemConfigService $systemConfig)
    {
    }

    public function get(string $key): mixed
    {
        return $this->systemConfig->get(self::KEY_PREFIX . $key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->systemConfig->set(self::KEY_PREFIX . $key, $value);
    }

    public function delete(string $key): void
    {
        $this->systemConfig->delete(self::KEY_PREFIX . $key);
    }
}
