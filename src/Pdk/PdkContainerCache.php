<?php

declare(strict_types=1);

namespace MyParcelNL\Shopware\Pdk;

use FilesystemIterator;
use MyParcelNL\Pdk\Base\Pdk;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Throws away the PDK's compiled container.
 *
 * In production the PDK compiles its container definitions and lazy-loading
 * proxies into vendor/myparcelnl/pdk/.cache, and it never invalidates that itself.
 * After a plug-in update the compiled definitions describe the previous version,
 * so without this a merchant keeps running old wiring until something happens to
 * delete the file — a failure that is very hard to trace back to its cause.
 *
 * Deliberately static and boot-free. Booting the PDK to reach Pdk::clearCache()
 * through its facade would load the very container we are about to discard, and
 * this has to work while the plug-in is inactive too: Shopware updates inactive
 * plug-ins as well, and then none of our services are in its container.
 *
 * Two things this covers that Pdk::clearCache() does not:
 *
 * - It recurses. PDK 4.1 compiles straight into .cache, but 4.4 compiles into a
 *   version-named subdirectory, and the plug-in allows ^4.1. A flat scan silently
 *   stops finding anything the moment the PDK layout changes underneath us, which
 *   would reintroduce exactly the bug this class exists to prevent.
 * - It removes proxies as well as the compiled container. Those go stale on an
 *   update for the same reason, and everything here is regenerated on demand.
 *
 * It also drops php-di's APCu definition cache, which lives outside the directory
 * entirely. PDK 4.1 namespaces it by a fixed string, so stale definitions would
 * otherwise survive an update and get baked straight back into the newly compiled
 * container; 4.4 namespaces it per cache version and no longer needs the help.
 */
final class PdkContainerCache
{
    /**
     * php-di's SourceCache key prefix, with the namespace the PDK asks for. Matches
     * both "pdk-definition-cache" (4.1) and "pdk-definition-cache-<version>" (4.4).
     *
     * @see \MyParcelNL\Pdk\Base\Factory\PdkFactory::setupCache()
     */
    private const DEFINITION_CACHE_KEY_PATTERN = '/^php-di\.definitions\.pdk-definition-cache/';

    /**
     * @return int the number of files removed
     */
    public static function clear(): int
    {
        $removed = self::clearCompiledFiles();

        self::clearDefinitionCache();

        return $removed;
    }

    private static function clearCompiledFiles(): int
    {
        $directory = Pdk::CACHE_DIR;

        if (!is_dir($directory)) {
            return 0;
        }

        $removed  = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            // Children before parents, so a directory is empty by the time we reach it.
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            // The compiled container is a PHP file that gets rewritten at the same
            // path, so opcache has to be told as well or it keeps serving the old one.
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($item->getPathname(), true);
            }

            if (@unlink($item->getPathname())) {
                $removed++;
            }
        }

        return $removed;
    }

    private static function clearDefinitionCache(): void
    {
        if (!function_exists('apcu_delete') || !class_exists(\APCUIterator::class)) {
            return;
        }

        @apcu_delete(new \APCUIterator(self::DEFINITION_CACHE_KEY_PATTERN));
    }
}
