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
 * service. A queue-based version (Shopware Messenger) replaces this class in
 * the fulfilment ticket; until then nothing can be scheduled for later.
 */
final class SynchronousCronService implements CronServiceInterface
{
    /**
     * @param  callable|string|callable-string $callback
     */
    public function dispatch($callback, ...$args): void
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('The cron callback cannot be called.');
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
}
