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
        // PdkEndpoint::call()'s own PHPDoc still names the real (unscoped)
        // Symfony\Component\HttpFoundation\Request: php-scoper only rewrites a
        // docblock FQCN when the file also imports it through a `use` statement,
        // and this vendor file does not import Request, only Response and
        // JsonResponse. The method's actual runtime check
        // (PdkActionsService::createRequest()) is `instanceof` the scoped Request
        // we build above, so this call is correct; only the vendor docblock is stale.
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
