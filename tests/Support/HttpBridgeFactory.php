<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Http\PdkHttpBridge;
use MyParcel\Shopware\Pdk\PdkInitializer;

/**
 * A bridge whose initializer is never booted: the tests call the conversion
 * steps and run() directly.
 */
final class HttpBridgeFactory
{
    public static function create(RecordingLogger $logger, bool $debug = true): PdkHttpBridge
    {
        $initializer = new PdkInitializer(
            $logger,
            new InMemoryConfigStorage(),
            new FixedLocaleResolver(null),
            new FixedUrlResolver([]),
            '0.0.0-test',
            'https://shop.test'
        );

        return new PdkHttpBridge($initializer, $logger, $debug);
    }
}
