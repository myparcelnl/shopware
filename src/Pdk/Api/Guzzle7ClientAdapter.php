<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Api;

use _MyParcel\GuzzleHttp\Client;
use _MyParcel\GuzzleHttp\Exception\GuzzleException;
use _MyParcel\GuzzleHttp\RequestOptions;
use MyParcelNL\Pdk\Api\Contract\ClientAdapterInterface;
use MyParcelNL\Pdk\Api\Contract\ClientResponseInterface;
use MyParcelNL\Pdk\Api\Response\ClientResponse;
use MyParcelNL\Pdk\Logger\Contract\PdkLoggerInterface;
use Psr\Log\LogLevel;

/**
 * Sends the PDK's HTTP requests with the Guzzle copy bundled in the scoped
 * vendor, so the plugin does not depend on the Guzzle version Shopware ships.
 *
 * Error statuses go back to the PDK as a response, not as an exception: the
 * PDK decides what a 4xx or 5xx means. Only a request that got no response at
 * all (connection failure, time-out) throws, after it is logged.
 */
final class Guzzle7ClientAdapter implements ClientAdapterInterface
{
    private const CONNECT_TIMEOUT_SECONDS = 10;
    private const TIMEOUT_SECONDS         = 60;

    public function __construct(private readonly Client $client, private readonly PdkLoggerInterface $logger)
    {
    }

    /**
     * @param  array{headers?: array<string, string|string[]>, body?: string} $options
     *
     * @throws \_MyParcel\GuzzleHttp\Exception\GuzzleException
     */
    public function doRequest(string $httpMethod, string $uri, array $options = []): ClientResponseInterface
    {
        $requestOptions = array_filter([
            RequestOptions::HTTP_ERRORS     => false,
            RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            RequestOptions::TIMEOUT         => self::TIMEOUT_SECONDS,
            RequestOptions::HEADERS         => $options['headers'] ?? null,
            RequestOptions::BODY            => $options['body'] ?? null,
        ], static fn ($value) => null !== $value);

        $start = microtime(true);

        try {
            $response = $this->client->request(strtolower($httpMethod), $uri, $requestOptions);
        } catch (GuzzleException $exception) {
            // ->log() directly, not ->error(): the PDK's AbstractLogger would
            // prefix the message with "[PDK]: " otherwise.
            // Headers and body stay out of the log: they carry the API key and
            // customer data.
            $this->logger->log(LogLevel::ERROR, 'MyParcel API request failed', [
                'method'     => strtoupper($httpMethod),
                'uri'        => $uri,
                'exception'  => get_class($exception),
                'message'    => $exception->getMessage(),
                'durationMs' => (int) round((microtime(true) - $start) * 1000),
            ]);

            throw $exception;
        }

        $body = $response->getBody();

        return new ClientResponse(
            $body->isReadable() ? $body->getContents() : null,
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }
}
