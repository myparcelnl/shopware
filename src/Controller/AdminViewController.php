<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Controller;

use MyParcel\Shopware\Admin\AdminAssets;
use MyParcel\Shopware\Pdk\Frontend\AdminViewNotFoundException;
use MyParcel\Shopware\Pdk\Frontend\AdminViewRendererInterface;
use MyParcel\Shopware\Pdk\Http\PdkHttpBridge;
use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Pdk\View\AdminViewRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The Shopware admin is a single-page app, so no PHP page holds the PDK
 * markup. The admin module asks for it here and inserts it.
 */
final class AdminViewController
{
    public function __construct(
        private readonly PdkHttpBridge $bridge,
        private readonly AdminViewRendererInterface $renderer,
        private readonly AdminAssets $assets
    ) {
    }

    /**
     * Renders one PDK admin view for the admin module, with the order list
     * items or the order or product it needs. Answers 400 for an unknown view
     * or missing context, without booting the PDK, and 404 for an order that
     * does not exist.
     */
    #[Route(
        path: '/api/_action/myparcel/view',
        name: RouteName::ADMIN_VIEW,
        defaults: ['_routeScope' => ['api'], '_acl' => ['myparcel:access']],
        methods: ['GET']
    )]
    public function view(Request $request): Response
    {
        $viewRequest = AdminViewRequest::fromQuery($request->query->all());

        if (null === $viewRequest) {
            return new JsonResponse(['message' => 'Unknown MyParcel admin view or invalid IDs.'], Response::HTTP_BAD_REQUEST);
        }

        $request->attributes->set(AdminView::REQUEST_ATTRIBUTE, $viewRequest->view);

        return $this->bridge->run(function () use ($viewRequest): Response {
            try {
                $result = $this->renderer->render($viewRequest);
            } catch (AdminViewNotFoundException $exception) {
                return new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse([
                'html'        => $result->html,
                // An object, so the admin app reads a map even when it holds one order.
                'items'       => (object) $result->items,
                'bulkActions' => $result->bulkActions,
                'scripts'     => $this->assets->getScripts(),
                'styles'      => $this->assets->getStyles(),
            ]);
        });
    }
}
