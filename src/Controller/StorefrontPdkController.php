<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Controller;

use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\Http\PdkHttpBridge;
use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcelNL\Pdk\App\Api\PdkEndpoint;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The checkout's way into the PDK. XmlHttpRequest: true, because Shopware
 * refuses an XHR to a storefront route that does not allow it.
 */
final class StorefrontPdkController
{
    public function __construct(
        private readonly PdkHttpBridge $bridge,
        private readonly SalesChannelContextHolder $contextHolder
    ) {
    }

    /**
     * Runs a PDK frontend action for the checkout. The action comes from the
     * "action" query parameter; the PDK answer goes back unchanged. The
     * context goes to the holder first: the checkout context action reads the
     * consumer's cart.
     */
    #[Route(
        path: '/myparcel/pdk',
        name: RouteName::STOREFRONT_PDK,
        defaults: ['_routeScope' => ['storefront'], 'XmlHttpRequest' => true],
        methods: ['GET', 'POST']
    )]
    public function pdk(Request $request, SalesChannelContext $context): Response
    {
        $this->contextHolder->set($context);

        return $this->bridge->callEndpoint($request, PdkEndpoint::CONTEXT_FRONTEND);
    }
}
