<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Controller;

use MyParcel\Shopware\Admin\AdminAssets;
use MyParcel\Shopware\Pdk\Frontend\AdminViewRendererInterface;
use MyParcel\Shopware\Pdk\Http\PdkHttpBridge;
use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcel\Shopware\Pdk\View\AdminView;
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

    #[Route(
        path: '/api/_action/myparcel/view',
        name: RouteName::ADMIN_VIEW,
        defaults: ['_routeScope' => ['api'], '_acl' => ['myparcel:access']],
        methods: ['GET']
    )]
    public function view(Request $request): Response
    {
        $value = $request->query->all()['view'] ?? null;
        $view  = is_string($value) ? AdminView::tryFrom($value) : null;

        if (null === $view) {
            return new JsonResponse(['message' => 'Unknown MyParcel admin view.'], Response::HTTP_BAD_REQUEST);
        }

        $request->attributes->set(AdminView::REQUEST_ATTRIBUTE, $view);

        return $this->bridge->run(fn (): Response => new JsonResponse([
            'html'    => $this->renderer->render($view),
            'scripts' => $this->assets->getScripts(),
            'styles'  => $this->assets->getStyles(),
        ]));
    }
}
