<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Logger\PdkLogger;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use MyParcelNL\Pdk\Logger\AbstractLogger;
use Psr\Log\LogLevel;

it('is a pdk logger', function () {
    expect(new PdkLogger(new RecordingLogger()))->toBeInstanceOf(AbstractLogger::class);
});

it('forwards a record to the injected psr logger', function () {
    $psrLogger = new RecordingLogger();

    (new PdkLogger($psrLogger))->log(LogLevel::WARNING, 'something happened', ['order' => 42]);

    expect($psrLogger->records)->toBe([
        ['level' => LogLevel::WARNING, 'message' => 'something happened', 'context' => ['order' => 42]],
    ]);
});

it('casts a stringable message to a string', function () {
    $psrLogger = new RecordingLogger();
    $message   = new class {
        public function __toString(): string
        {
            return 'from an object';
        }
    };

    (new PdkLogger($psrLogger))->log(LogLevel::INFO, $message);

    expect($psrLogger->records[0]['message'])->toBeString()->toBe('from an object');
});
