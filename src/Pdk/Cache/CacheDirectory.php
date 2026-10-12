<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Cache;

/**
 * Tells whether the PDK can write its compiled container.
 *
 * The PDK compiles into a fixed directory inside its own package, and PHP-DI
 * throws on every boot when it cannot write there, e.g. on a host with a
 * read-only vendor directory.
 */
final class CacheDirectory
{
    public static function isWritable(string $directory): bool
    {
        if (is_dir($directory)) {
            return is_writable($directory);
        }

        $parent = dirname($directory);

        return is_dir($parent) && is_writable($parent);
    }
}
