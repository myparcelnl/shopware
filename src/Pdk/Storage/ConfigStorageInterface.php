<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Storage;

/**
 * One installation-wide value per key, persisted across requests and cache
 * clears.
 *
 * A seam of our own rather than Shopware's SystemConfigService directly: the
 * unit tests run against the unscoped development vendor, which has no
 * Shopware in it.
 */
interface ConfigStorageInterface
{
    /**
     * @return mixed null when nothing is stored under the key
     */
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function delete(string $key): void;
}
