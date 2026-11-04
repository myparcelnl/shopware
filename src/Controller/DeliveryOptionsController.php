<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Controller;

use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Checkout\CartLoaderInterface;
use MyParcel\Shopware\Checkout\DeliveryOptionsGatewayInterface;
use MyParcel\Shopware\Pdk\Routing\RouteName;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Stores the consumer's choice of delivery options on the cart. The checkout
 * script calls it every time the widget reports a new choice, and then reloads
 * the cart summary: the cart now has the fee lines and a new total, and with
 * that a new order hash.
 */
final class DeliveryOptionsController
{
    public function __construct(
        private readonly DeliveryOptionsGatewayInterface $gateway,
        private readonly CartLoaderInterface $cartLoader,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Takes a JSON body {"deliveryOptions": {...}} with the output of the widget.
     * Answers 204 when the choice is stored, 400 when the body or the choice is
     * not valid.
     */
    #[Route(
        path: '/myparcel/delivery-options',
        name: RouteName::STOREFRONT_DELIVERY_OPTIONS,
        defaults: ['_routeScope' => ['storefront'], 'XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function store(Request $request, SalesChannelContext $context): Response
    {
        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::badRequest('The body is not valid JSON.');
        }

        $input = is_array($body) ? ($body['deliveryOptions'] ?? null) : null;

        if (!is_array($input) || [] === $input) {
            return self::badRequest('The body has no deliveryOptions object.');
        }

        try {
            $deliveryOptions = $this->gateway->normalize($input, $context);
        } catch (Throwable $exception) {
            $this->logger->warning('MyParcel: the checkout sent delivery options that the PDK cannot read', ['exception' => $exception]);

            return self::badRequest('The delivery options are not valid.');
        }

        $cart = $this->cartLoader->load($context);

        CartDeliveryOptions::store($cart, $deliveryOptions, $context);

        $this->cartLoader->recalculate($cart, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private static function badRequest(string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], Response::HTTP_BAD_REQUEST);
    }
}
