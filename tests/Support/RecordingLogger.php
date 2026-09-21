<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * A PSR logger that keeps its records instead of writing them, so a test can
 * assert what the plug-in handed to Shopware.
 */
final class RecordingLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var array<int, array{level: mixed, message: string, context: array}>
     */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
