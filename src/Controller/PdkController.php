<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Controller;

use MyParcelNL\Pdk\App\Api\PdkEndpoint;
use MyParcelNL\Pdk\Facade\Pdk as PdkFacade;
use MyParcel\Shopware\Pdk\PdkInitializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PoC: routes an HTTP request from Shopware through the scoped PDK endpoint.
 * The request/response conversion marks the boundary between Shopware's real
 * Symfony runtime and the _MyParcel-scoped copy inside the plugin vendor.
 */
class PdkController
{
    public function __construct(private readonly PdkInitializer $pdkInitializer)
    {
    }

    #[Route(
        path: '/myparcel/pdk',
        name: 'frontend.myparcel.pdk',
        defaults: ['_routeScope' => ['storefront']],
        methods: ['GET']
    )]
    public function pdk(Request $request): Response
    {
        $this->pdkInitializer->boot();

        $scopedRequest = \_MyParcel\Symfony\Component\HttpFoundation\Request::create(
            $request->getUri(),
            $request->getMethod(),
            $request->query->all()
        );

        /** @var PdkEndpoint $endpoint */
        $endpoint = PdkFacade::get(PdkEndpoint::class);
        // PdkEndpoint::call()'s docblock still names the unscoped
        // Symfony\Component\HttpFoundation\Request: php-scoper does not rewrite a
        // docblock FQCN unless the file also imports the class, and PdkEndpoint.php
        // imports only Response and JsonResponse.
        //
        // PdkActionsService type-checks against the scoped
        // _MyParcel\Symfony\Component\HttpFoundation\Request, which is what
        // $scopedRequest is, so this call is correct.
        //
        // Temporary: this ignore comes out once the PDK ships the missing import,
        // tracked in INT-1948. PHPStan then reports it as unmatched, so the analysis
        // fails until somebody deletes these lines.
        // @phpstan-ignore argument.type
        $scopedResponse = $endpoint->call($scopedRequest, PdkEndpoint::CONTEXT_BACKEND);

        // Forward the PDK's own headers rather than forcing JSON: it sets the
        // content type itself, and actions may add cache or attachment headers.
        return new Response(
            (string) $scopedResponse->getContent(),
            $scopedResponse->getStatusCode(),
            $scopedResponse->headers->all()
        );
    }
}
