<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Controller;

use MyParcel\Shopware\Checkout\CartDeliveryOptions;
use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcelNL\Pdk\Shipment\Model\DeliveryOptions;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
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
    /**
     * The keys of the widget output that the order needs. isPickup follows from
     * deliveryType.
     */
    private const KEYS = ['carrier', 'date', 'deliveryType', 'packageType', 'pickupLocation', 'shipmentOptions'];

    /**
     * The shipment options that the widget can send. Each one has a price in the
     * carrier settings.
     */
    private const SHIPMENT_OPTIONS = ['signature', 'onlyRecipient', 'priorityDelivery', 'sameDayDelivery'];

    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly SalesChannelContextHolder $contextHolder,
        private readonly CartService $cartService,
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
            $deliveryOptions = $this->normalize(self::sanitize($input), $context);
        } catch (Throwable $exception) {
            $this->logger->warning('MyParcel: the checkout sent delivery options that the PDK cannot read', ['exception' => $exception]);

            return self::badRequest('The delivery options are not valid.');
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);

        CartDeliveryOptions::store($cart, $deliveryOptions, $context);

        $this->cartService->recalculate($cart, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Turns the output of the widget into the form that the PDK stores on an
     * order, as the WooCommerce plugin does.
     *
     * @param  array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function normalize(array $input, SalesChannelContext $context): array
    {
        $this->contextHolder->set($context);
        $this->pdkInitializer->boot();

        $deliveryOptions = new DeliveryOptions($input);

        // Reading the carrier resolves it through the carrier repository, which
        // throws for a carrier the account does not have.
        $deliveryOptions->getCarrierAttribute();

        return $deliveryOptions->toStorableArray();
    }

    /**
     * Keeps only what the widget can send, and checks its shape before the PDK
     * model gets it. The model creates an object of a class whose name is the
     * value of a class-cast attribute, and it stores every attribute it knows,
     * also labelAmount and contractId.
     *
     * @TODO INT-2353: move this check to the PDK, which the WooCommerce plugin also needs.
     *
     * @param  array<mixed> $input
     *
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException when the input is not what the widget sends
     */
    private static function sanitize(array $input): array
    {
        $input = array_intersect_key($input, array_flip(self::KEYS));

        foreach (['carrier', 'deliveryType', 'packageType'] as $key) {
            if (isset($input[$key]) && !is_string($input[$key])) {
                throw new \InvalidArgumentException(sprintf('%s is not a string', $key));
            }
        }

        if (isset($input['date']) && !(is_string($input['date']) && false !== strtotime($input['date']))) {
            throw new \InvalidArgumentException('date is not a date');
        }

        $shipmentOptions = $input['shipmentOptions'] ?? [];

        if (!is_array($shipmentOptions)
            || [] !== array_diff(array_keys($shipmentOptions), self::SHIPMENT_OPTIONS)
            || [] !== array_filter($shipmentOptions, static fn ($value) => !is_bool($value))) {
            throw new \InvalidArgumentException('shipmentOptions holds an option that the widget does not send');
        }

        if ('pickup' !== ($input['deliveryType'] ?? null)) {
            unset($input['pickupLocation']);

            return $input;
        }

        $pickupLocation = $input['pickupLocation'] ?? null;

        if (!is_array($pickupLocation) || [] === $pickupLocation
            || [] !== array_filter($pickupLocation, static fn ($value) => null !== $value && !is_scalar($value))) {
            throw new \InvalidArgumentException('a pickup needs a pickupLocation');
        }

        return $input;
    }

    private static function badRequest(string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], Response::HTTP_BAD_REQUEST);
    }
}
