<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Controller;

use MyParcel\Shopware\Pdk\Http\PdkHttpBridge;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcel\Shopware\Pdk\Webhook\WebhookUrlValidator;
use MyParcelNL\Pdk\App\Webhook\Contract\PdkWebhooksRepositoryInterface;
use MyParcelNL\Pdk\Facade\Pdk;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Receives MyParcel webhooks. MyParcel has no Shopware token, so the route is
 * public and has no sales channel: the api scope with auth_required false, the
 * same as /api/oauth/token. The hash in the URL is the only protection, so it
 * is checked here, before the PDK answers 202 to anything.
 *
 * The hash defaults to '', so the router can build the base URL without one.
 */
final class WebhookController
{
    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly PdkHttpBridge $bridge,
        private readonly WebhookUrlValidator $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/api/myparcel/webhook/{hash}',
        name: RouteName::WEBHOOK,
        defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'hash' => ''],
        methods: ['POST']
    )]
    public function webhook(Request $request): Response
    {
        return $this->bridge->run(function () use ($request): Response {
            $this->pdkInitializer->boot();

            /** @var PdkWebhooksRepositoryInterface $repository */
            $repository = Pdk::get(PdkWebhooksRepositoryInterface::class);

            if (!$this->validator->isValid($repository->getHashedUrl(), $request->getRequestUri())) {
                // No URL or hash in the log: either one would give the secret away.
                $this->logger->warning('MyParcel webhook rejected: the URL does not match the stored webhook URL');

                return new Response('', Response::HTTP_NOT_FOUND);
            }

            return $this->bridge->callWebhook($request);
        });
    }
}
