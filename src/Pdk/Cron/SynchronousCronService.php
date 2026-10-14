<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Cron;

use InvalidArgumentException;
use MyParcelNL\Pdk\Base\Contract\CronServiceInterface;
use RuntimeException;

/**
 * Runs PDK work in the current request.
 *
 * The webhook manager dispatches the processing of a webhook through this
 * service.
 *
 * @TODO INT-1962: replace this class with a queue-based cron service (Shopware
 * Messenger). Until then, nothing can be scheduled for later.
 */
final class SynchronousCronService implements CronServiceInterface
{
    /**
     * @param  callable|string|callable-string $callback
     */
    public function dispatch($callback, ...$args): void
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException(
                sprintf('The cron callback %s cannot be called.', self::describe($callback))
            );
        }

        $callback(...$args);
    }

    /**
     * The contract requires an exception when the platform cannot schedule, so a
     * caller is never told that work is queued when it is not.
     *
     * @param  callable|string|callable-string $callback
     */
    public function schedule($callback, int $timestamp, ...$args): void
    {
        throw new RuntimeException('Scheduling is not available: the plugin has no queue-based cron service yet.');
    }

    /**
     * A callback can be an array or another non-string type, so it cannot go
     * into the message as it is.
     */
    private static function describe(mixed $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback) && isset($callback[0], $callback[1]) && is_string($callback[1])) {
            $target = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];

            return sprintf('%s::%s', is_string($target) ? $target : get_debug_type($target), $callback[1]);
        }

        return get_debug_type($callback);
    }
}
