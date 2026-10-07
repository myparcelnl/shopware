<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Http;

use _MyParcel\Symfony\Component\HttpFoundation\BinaryFileResponse as PdkBinaryFileResponse;
use _MyParcel\Symfony\Component\HttpFoundation\Request as PdkRequest;
use _MyParcel\Symfony\Component\HttpFoundation\Response as PdkResponse;
use Closure;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcelNL\Pdk\App\Api\PdkEndpoint;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhookManagerInterface;
use MyParcelNL\Pdk\Facade\Pdk;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The one place where Shopware's Symfony runtime meets the _MyParcel-scoped
 * copy inside the plugin vendor. Every route goes through here, so no other
 * class in the plugin names a scoped Symfony class.
 */
final class PdkHttpBridge
{
    private const GENERIC_ERROR_MESSAGE = 'The MyParcel request failed. The MyParcel log has the details.';

    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly LoggerInterface $logger,
        private readonly bool $debug
    ) {
    }

    public function callEndpoint(Request $request, string $context): Response
    {
        return $this->run(function () use ($request, $context): Response {
            $this->pdkInitializer->boot();

            /** @var PdkEndpoint $endpoint */
            $endpoint = Pdk::get(PdkEndpoint::class);

            // PdkEndpoint::call()'s docblock still names the unscoped
            // Symfony\Component\HttpFoundation\Request: php-scoper does not rewrite a
            // docblock FQCN unless the file also imports the class. PdkActionsService
            // type-checks against the scoped class, which is what we pass.
            //
            // Temporary: this ignore comes out once the PDK ships the missing import,
            // tracked in INT-1948. PHPStan then reports it as unmatched.
            // @phpstan-ignore argument.type
            return $this->toShopwareResponse($endpoint->call($this->toPdkRequest($request), $context));
        });
    }

    public function callWebhook(Request $request): Response
    {
        return $this->run(function () use ($request): Response {
            $this->pdkInitializer->boot();

            /** @var PdkWebhookManagerInterface $webhookManager */
            $webhookManager = Pdk::get(PdkWebhookManagerInterface::class);

            return $this->toShopwareResponse($webhookManager->call($this->toPdkRequest($request)));
        });
    }

    /**
     * PdkEndpoint catches what goes wrong inside an action. This catches what
     * goes wrong around it: booting, converting, resolving a service. The
     * message can hold internals and the routes can be anonymous, so only
     * debug mode shows it to the caller; the log always has the full exception.
     *
     * @param  Closure(): Response $call
     */
    public function run(Closure $call): Response
    {
        try {
            return $call();
        } catch (Throwable $exception) {
            $this->logger->error('MyParcel request failed before the PDK could answer', ['exception' => $exception]);

            return new JsonResponse(
                ['message' => $this->debug ? $exception->getMessage() : self::GENERIC_ERROR_MESSAGE],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Attributes hold Shopware objects the PDK cannot use, and no PDK action
     * takes uploads, so neither is copied. The server parameters carry the
     * headers, the method and REQUEST_URI, which the webhook manager compares.
     */
    public function toPdkRequest(Request $request): PdkRequest
    {
        return new PdkRequest(
            $request->query->all(),
            $request->request->all(),
            [],
            $request->cookies->all(),
            [],
            $request->server->all(),
            $request->getContent()
        );
    }

    /**
     * The PDK sets the content type itself, and actions may add cache or
     * attachment headers, so every header is forwarded as it is.
     */
    public function toShopwareResponse(PdkResponse $response): Response
    {
        if ($response instanceof PdkBinaryFileResponse) {
            $converted = new BinaryFileResponse(
                $response->getFile()->getPathname(),
                $response->getStatusCode(),
                $response->headers->all()
            );

            if ($this->deletesFileAfterSend($response)) {
                $converted->deleteFileAfterSend();
            }

            return $converted;
        }

        return new Response((string) $response->getContent(), $response->getStatusCode(), $response->headers->all());
    }

    /**
     * BinaryFileResponse has no getter for this flag.
     */
    private function deletesFileAfterSend(PdkBinaryFileResponse $response): bool
    {
        return (bool) (new ReflectionProperty(PdkBinaryFileResponse::class, 'deleteFileAfterSend'))->getValue($response);
    }
}
