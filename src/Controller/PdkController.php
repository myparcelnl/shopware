<?php

declare(strict_types=1);

namespace MyParcelNL\Shopware\Controller;

use MyParcelNL\Pdk\App\Api\PdkEndpoint;
use MyParcelNL\Pdk\Facade\Pdk as PdkFacade;
use MyParcelNL\Shopware\Pdk\PdkInitializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PoC: routes an HTTP request from Shopware through the scoped PDK endpoint.
 * The request/response conversion marks the boundary between Shopware's real
 * Symfony runtime and the _MyParcelNL-scoped copy inside the plugin vendor.
 */
class PdkController
{
    public function __construct(private readonly PdkInitializer $pdkInitializer)
    {
    }

    #[Route(
        path: '/myparcelnl/pdk',
        name: 'frontend.myparcelnl.pdk',
        defaults: ['_routeScope' => ['storefront']],
        methods: ['GET']
    )]
    public function pdk(Request $request): Response
    {
        $this->pdkInitializer->boot();

        $scopedRequest = \_MyParcelNL\Symfony\Component\HttpFoundation\Request::create(
            $request->getUri(),
            $request->getMethod(),
            $request->query->all()
        );

        /** @var PdkEndpoint $endpoint */
        $endpoint       = PdkFacade::get(PdkEndpoint::class);
        $scopedResponse = $endpoint->call($scopedRequest, PdkEndpoint::CONTEXT_BACKEND);

        return new Response(
            (string) $scopedResponse->getContent(),
            $scopedResponse->getStatusCode(),
            ['Content-Type' => 'application/json']
        );
    }
}
