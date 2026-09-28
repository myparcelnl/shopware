<?php

declare(strict_types=1);

use _MyParcel\GuzzleHttp\Client;
use _MyParcel\GuzzleHttp\Exception\ConnectException;
use _MyParcel\GuzzleHttp\Handler\MockHandler;
use _MyParcel\GuzzleHttp\HandlerStack;
use _MyParcel\GuzzleHttp\Middleware;
use _MyParcel\GuzzleHttp\Psr7\Request;
use _MyParcel\GuzzleHttp\Psr7\Response;
use MyParcel\Shopware\Pdk\Api\Guzzle7ClientAdapter;
use MyParcel\Shopware\Pdk\Logger\PdkLogger;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use MyParcelNL\Pdk\Api\Contract\ClientAdapterInterface;
use Psr\Log\LogLevel;

/**
 * @param  array<int, mixed> $queue
 * @param  array<int, array> $history filled with the requests the client sent
 */
function adapter(array $queue, RecordingLogger $logger, array &$history = []): Guzzle7ClientAdapter
{
    $stack = HandlerStack::create(new MockHandler($queue));
    $stack->push(Middleware::history($history));

    return new Guzzle7ClientAdapter(new Client(['handler' => $stack]), new PdkLogger($logger));
}

it('is a pdk client adapter', function () {
    expect(adapter([], new RecordingLogger()))->toBeInstanceOf(ClientAdapterInterface::class);
});

it('returns an error status to the pdk instead of throwing', function () {
    $response = adapter([new Response(422, ['X-Request-Id' => 'abc'], '{"errors":[]}')], new RecordingLogger())
        ->doRequest('GET', 'https://api.myparcel.nl/accounts');

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getBody())->toBe('{"errors":[]}')
        ->and($response->getHeaders()['X-Request-Id'])->toBe(['abc']);
});

it('sends method, headers, body and time-outs', function () {
    $history = [];

    adapter([new Response(200, [], '{}')], new RecordingLogger(), $history)
        ->doRequest('POST', 'https://api.myparcel.nl/shipments', [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => '{"data":[]}',
        ]);

    $sent = $history[0];

    expect($sent['request']->getMethod())->toBe('POST')
        ->and($sent['request']->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and((string) $sent['request']->getBody())->toBe('{"data":[]}')
        ->and($sent['options']['http_errors'])->toBeFalse()
        ->and($sent['options']['connect_timeout'])->toBe(10)
        ->and($sent['options']['timeout'])->toBe(60);
});

it('logs a network failure with context and throws it again', function () {
    $logger    = new RecordingLogger();
    $request   = new Request('GET', 'https://api.myparcel.nl/accounts', ['Authorization' => 'bearer do-not-log']);
    $exception = new ConnectException('Connection refused', $request);

    try {
        adapter([$exception], $logger)->doRequest('get', 'https://api.myparcel.nl/accounts', [
            'headers' => ['Authorization' => 'bearer do-not-log'],
        ]);
        $this->fail('The exception was not thrown again.');
    } catch (ConnectException $caught) {
        expect($caught)->toBe($exception);
    }

    expect($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe(LogLevel::ERROR)
        ->and($logger->records[0]['message'])->toBe('[PDK]: MyParcel API request failed')
        ->and($logger->records[0]['context'])->toMatchArray([
            'method'    => 'GET',
            'uri'       => 'https://api.myparcel.nl/accounts',
            // get_class(), not ConnectException::class: a class alias keeps the
            // original class name, so in the test run the logged name is the
            // unprefixed one.
            'exception' => get_class($exception),
            'message'   => 'Connection refused',
        ])
        ->and($logger->records[0]['context']['durationMs'])->toBeInt()
        ->and(json_encode($logger->records))->not->toContain('do-not-log');
});
