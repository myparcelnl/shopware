<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Http\PdkHttpBridge;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
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
            FakeEntityRepository::with(),
            new InMemoryProductStorage(),
            new EuroCurrency(FakeEntityRepository::with()),
            new OrderDataWriter(FakeEntityRepository::with(), FakeEntityRepository::with(), new RecordingConnection()),
            new RecordingOrderStateTransitioner(),
            '0.0.0-test',
            'https://shop.test',
            true
        );

        return new PdkHttpBridge($initializer, $logger, $debug);
    }
}
