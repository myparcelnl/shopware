<?php

declare(strict_types=1);

namespace MyParcelNL\Shopware\Pdk;

use MyParcelNL\Pdk\Base\Pdk;

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
 * Where Pdk::clearCache() removes only the compiled container, this clears the
 * whole directory. The generated proxies go stale on an update for exactly the
 * same reason, and everything in there is regenerated on the next request.
 */
final class PdkContainerCache
{
    /**
     * @return int the number of files removed
     */
    public static function clear(): int
    {
        $directory = Pdk::CACHE_DIR;

        if (!is_dir($directory)) {
            return 0;
        }

        $removed = 0;

        foreach (glob(rtrim($directory, '/') . '/*.php') ?: [] as $file) {
            if (is_file($file) && unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }
}
