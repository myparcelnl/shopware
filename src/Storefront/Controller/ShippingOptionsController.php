<?php

namespace Kiener\KienerMyParcel\Storefront\Controller;

use Exception;
use Kiener\KienerMyParcel\Core\Content\ShippingOption\ShippingOptionEntity;
use Kiener\KienerMyParcel\Service\Order\OrderService;
use Kiener\KienerMyParcel\Service\ShippingOptions\ShippingOptionsService;
use MyParcelNL\Sdk\src\Model\Consignment\AbstractConsignment;
use MyParcelNL\Sdk\src\Model\Consignment\BpostConsignment;
use MyParcelNL\Sdk\src\Model\Consignment\DPDConsignment;
use MyParcelNL\Sdk\src\Model\Consignment\PostNLConsignment;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ShippingOptionsController extends StorefrontController
{
    public const ROUTE_NAME_ALL = 'api.action.myparcel.shipping_options.all';
    public const ROUTE_NAME_CREATE = 'api.action.myparcel.shipping_options.create';
    public const ROUTE_NAME_SHOW = 'api.action.myparcel.shipping_options.show';

    private const RESPONSE_KEY_SUCCESS = 'success';
    private const RESPONSE_KEY_ERROR = 'error';
    private const RESPONSE_KEY_SHIPPING_OPTIONS = 'shipping_options';
    private const RESPONSE_KEY_DELIVERY_TYPES = 'delivery_types';

    public const REQUEST_KEY_SHIPPING_OPTIONS_ID = 'shipping_options_id';
    public const REQUEST_KEY_ORDER_ID = 'order_id';
    public const REQUEST_KEY_ORDER_VERSION_ID = 'order_version_id';
    public const REQUEST_KEY_CARRIER_ID = 'carrier_id';
    public const REQUEST_KEY_AGE_CHECK = 'age_check';
    public const REQUEST_KEY_LARGE_FORMAT = 'large_format';
    public const REQUEST_KEY_RETURN_IF_NOT_HOME = 'return_if_not_home';
    public const REQUEST_KEY_REQUIRES_SIGNATURE = 'requires_signature';
    public const REQUEST_KEY_ONLY_RECIPIENT = 'only_recipient';
    public const REQUEST_KEY_PACKAGE_TYPE = 'package_type';
    public const REQUEST_KEY_DELIVERY_TYPE = 'package_type';

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var ShippingOptionsService
     */
    private $shippingOptionsService;

    /**
     * ShippingOptionsController constructor.
     *
     * @param OrderService           $orderService
     * @param ShippingOptionsService $shippingOptionsService
     */
    public function __construct(
        OrderService $orderService,
        ShippingOptionsService $shippingOptionsService
    )
    {
        $this->orderService = $orderService;
        $this->shippingOptionsService = $shippingOptionsService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/carriers",
     *     defaults={"auth_enabled"=true},
     *     name=ConsignmentController::ROUTE_NAME_GET_CARRIERS,
     *     methods={"GET"}
     *     )
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function getDeliveryTypes(): JsonResponse
    {
        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => true,
            self::RESPONSE_KEY_DELIVERY_TYPES => $this->shippingOptionsService->getDeliveryTypes(),

        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/shipping-options/create",
     *     defaults={"auth_enabled"=true},
     *     name=ShippingOptionsController::ROUTE_NAME_CREATE,
     *     methods={"POST"}
     *     )
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function createForOrder(Request $request): JsonResponse //NOSONAR
    {
        $orderId = $request->get(self::REQUEST_KEY_ORDER_ID);
        $orderVersionId = $request->get(self::REQUEST_KEY_ORDER_VERSION_ID);

        if ((string)$orderId === '' || (string)$orderVersionId === '') {
            return new JsonResponse([
                self::RESPONSE_KEY_SUCCESS => false,
                self::RESPONSE_KEY_ERROR => sprintf(
                    'Request is missing a valid %s or %s',
                    self::REQUEST_KEY_ORDER_ID,
                    self::REQUEST_KEY_ORDER_VERSION_ID
                )
            ]);
        }

        $context = new Context(new SystemSource());

        $order = $this->orderService->getOrder($orderId, $orderVersionId, $context);

        if ($order === null) {
            return new JsonResponse([
                self::RESPONSE_KEY_SUCCESS => false,
                self::RESPONSE_KEY_ERROR => sprintf(
                    'Could not find an order with id %s and version id %s',
                    $orderId,
                    $orderVersionId
                )
            ]);
        }

        $shippingOptions = [
            ShippingOptionEntity::FIELD_ORDER => [
                ShippingOptionEntity::FIELD_ORDER_ID => $orderId,
                ShippingOptionEntity::FIELD_VERSION_ID => $orderVersionId,
            ],
        ];

        $packageType = $request->get(self::REQUEST_KEY_PACKAGE_TYPE);

        if ((string)$packageType !== ''
            && is_int($packageType)
            && in_array($packageType, AbstractConsignment::PACKAGE_TYPES_IDS, true)
        ) {
            $shippingOptions[ShippingOptionEntity::FIELD_PACKAGE_TYPE] = $packageType;
        }

        if ((string)$request->get(self::REQUEST_KEY_AGE_CHECK) !== '') {
            $shippingOptions[ShippingOptionEntity::FIELD_REQUIRES_AGE_CHECK] =
                $packageType === AbstractConsignment::PACKAGE_TYPE_PACKAGE && $request->get(self::REQUEST_KEY_AGE_CHECK);
        }

        if ((string)$request->get(self::REQUEST_KEY_LARGE_FORMAT) !== '') {
            $shippingOptions[ShippingOptionEntity::FIELD_LARGE_FORMAT] =
                $packageType === AbstractConsignment::PACKAGE_TYPE_PACKAGE && $request->get(self::REQUEST_KEY_LARGE_FORMAT);
        }

        if ((string)$request->get(self::REQUEST_KEY_RETURN_IF_NOT_HOME) !== '') {
            $shippingOptions[ShippingOptionEntity::FIELD_RETURN_IF_NOT_HOME] =
                $packageType === AbstractConsignment::PACKAGE_TYPE_PACKAGE && $request->get(self::REQUEST_KEY_RETURN_IF_NOT_HOME);
        }

        if ((string)$request->get(self::REQUEST_KEY_REQUIRES_SIGNATURE) !== '') {
            $shippingOptions[ShippingOptionEntity::FIELD_REQUIRES_SIGNATURE] =
                $packageType === AbstractConsignment::PACKAGE_TYPE_PACKAGE && $request->get(self::REQUEST_KEY_REQUIRES_SIGNATURE);
        }

        if ((string)$request->get(self::REQUEST_KEY_ONLY_RECIPIENT) !== '') {
            $shippingOptions[ShippingOptionEntity::FIELD_ONLY_RECIPIENT] =
                $packageType === AbstractConsignment::PACKAGE_TYPE_PACKAGE && $request->get(self::REQUEST_KEY_ONLY_RECIPIENT);
        }

        $carrierId = $request->get(self::REQUEST_KEY_CARRIER_ID);

        if ((string)$carrierId !== ''
            && is_int($carrierId)
            && in_array($carrierId, [
                BpostConsignment::CARRIER_ID,
                DPDConsignment::CARRIER_ID,
                PostNLConsignment::CARRIER_ID,
            ], true)
        ) {
            $shippingOptions[ShippingOptionEntity::FIELD_CARRIER_ID] = $carrierId;
        }

        $deliveryType = $request->get(self::REQUEST_KEY_DELIVERY_TYPE);

        if ((string)$deliveryType !== ''
            && is_int($deliveryType)
            && in_array($deliveryType, AbstractConsignment::DELIVERY_TYPES_IDS, true)
        ) {
            $shippingOptions[ShippingOptionEntity::FIELD_DELIVERY_TYPE] = $deliveryType;

            if(in_array($deliveryType, [AbstractConsignment::DELIVERY_TYPE_MORNING, AbstractConsignment::DELIVERY_TYPE_EVENING], true))
            {
                $shippingOptions[ShippingOptionEntity::FIELD_ONLY_RECIPIENT] = true;
            }

        }

        $shippingOptionsEntity = $this->shippingOptionsService->createOrUpdateShippingOptions(
            $shippingOptions,
            $context
        );

        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => $shippingOptionsEntity !== null,
            self::RESPONSE_KEY_SHIPPING_OPTIONS => $shippingOptionsEntity
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/shipping-options/all",
     *     defaults={"auth_enabled"=true},
     *     name=ShippingOptionsController::ROUTE_NAME_ALL,
     *     methods={"GET"}
     *     )
     *
     * @return JsonResponse
     */
    public function all(): JsonResponse
    {
        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => true,
            self::RESPONSE_KEY_SHIPPING_OPTIONS => $this->shippingOptionsService->getAllShippingOptions(new Context(new SystemSource()))
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/shipping-options/show",
     *     defaults={"auth_enabled"=true},
     *     name=ShippingOptionsController::ROUTE_NAME_SHOW,
     *     methods={"POST"}
     *     )
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function show(Request $request): JsonResponse
    {
        $shippingOptionsId = $request->get(self::REQUEST_KEY_SHIPPING_OPTIONS_ID);

        if ((string)$shippingOptionsId === '') {
            return new JsonResponse([
                self::RESPONSE_KEY_SUCCESS => false,
                self::RESPONSE_KEY_ERROR => sprintf('Could not find Shipping Options with id %s', $shippingOptionsId)
            ]);
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        $shippingOptionsEntity = $this->shippingOptionsService->getShippingOptions($shippingOptionsId, $context);

        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => $shippingOptionsEntity !== null,
            self::RESPONSE_KEY_SHIPPING_OPTIONS => $shippingOptionsEntity
        ]);
    }
}