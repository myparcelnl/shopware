<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Http\PdkHttpBridge;

/**
 * A bridge whose initializer is never booted: the tests call the conversion
 * steps and run() directly.
 */
final class HttpBridgeFactory
{
    public static function create(RecordingLogger $logger, bool $debug = true): PdkHttpBridge
    {
        return new PdkHttpBridge(PdkServices::initializer($logger), $logger, $debug);
    }
}
