<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Logger;

use MyParcelNL\Pdk\Logger\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Forwards PDK log records to Shopware's "myparcel" Monolog channel, which
 * writes to var/log/myparcel_<env>-<date>.log.
 *
 * Extending the PDK's AbstractLogger rather than implementing a third-party
 * interface is deliberate: AbstractLogger keeps its MyParcelNL namespace under
 * php-scoper, so the scoping boundary stays out of our own code. The injected
 * LoggerInterface is the real, unprefixed PSR one — Psr is excluded from
 * scoping in scoper.inc.php, so this stays true in the release build.
 */
final class PdkLogger extends AbstractLogger
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * PSR log levels are plain strings in both the scoped and the real PSR
     * package, so the level passes straight through.
     */
    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, (string) $message, $context);
    }
}
