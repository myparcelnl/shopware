<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Controller;

use MyParcel\Shopware\Pdk\Http\PdkHttpBridge;
use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcelNL\Pdk\App\Api\PdkEndpoint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The admin app's way into the PDK. The api scope requires an admin API
 * token; myparcel:access becomes a role in the admin module (INT-1958). Until
 * then only users and integrations with all rights pass.
 */
final class AdminPdkController
{
    public function __construct(private readonly PdkHttpBridge $bridge)
    {
    }

    #[Route(
        path: '/api/_action/myparcel/pdk',
        name: RouteName::ADMIN_PDK,
        defaults: ['_routeScope' => ['api'], '_acl' => ['myparcel:access']],
        methods: ['GET', 'POST', 'PUT']
    )]
    public function pdk(Request $request): Response
    {
        return $this->bridge->callEndpoint($request, PdkEndpoint::CONTEXT_BACKEND);
    }
}
