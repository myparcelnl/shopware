<?php
/**
 * @noinspection PhpUnused
 * @noinspection PhpUndefinedClassInspection
 */

namespace Kiener\KienerMyParcel\Storefront\Controller;

use Exception;
use Kiener\KienerMyParcel\Core\Content\Shipment\ShipmentEntity;
use Kiener\KienerMyParcel\Service\Order\OrderService;
use Kiener\KienerMyParcel\Service\Shipment\ShipmentService;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ShipmentController extends StorefrontController
{
    public const ROUTE_NAME_ALL = 'api.action.myparcel.shipment.all';
    public const ROUTE_NAME_CREATE = 'api.action.myparcel.shipment.create';

    public const REQUEST_KEY_CONSIGNMENT_ID = 'consignment_id';
    public const REQUEST_KEY_SHIPPING_OPTION_ID = 'shipping_option_id';
    public const REQUEST_KEY_ORDER_ID = 'order_id';
    public const REQUEST_KEY_ORDER_VERSION_ID = 'order_version_id';
    public const REQUEST_KEY_LABEL_URL = 'label_url';
    public const REQUEST_KEY_INSURED_AMOUNT = 'insured_amount';

    private const RESPONSE_KEY_SUCCESS = 'success';
    private const RESPONSE_KEY_ERROR = 'error';
    private const RESPONSE_KEY_SHIPMENT = 'shipment';
    private const RESPONSE_KEY_SHIPMENTS = 'shipments';

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var ShipmentService
     */
    private $shipmentService;

    /**
     * ConsignmentController constructor.
     *
     * @param OrderService      $orderService
     * @param ShipmentService   $shipmentService
     */
    public function __construct(
        OrderService $orderService,
        ShipmentService $shipmentService
    )
    {
        $this->orderService = $orderService;
        $this->shipmentService = $shipmentService;
    }
    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/shipment/all",
     *     defaults={"auth_enabled"=true},
     *     name=ShipmentController::ROUTE_NAME_ALL,
     *     methods={"GET"}
     *     )
     *
     * @return JsonResponse
     */
    public function all(): JsonResponse
    {
        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => true,
            self::RESPONSE_KEY_SHIPMENTS => $this->shipmentService->getShipments(new Context(new SystemSource())),
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/shipment/create",
     *     defaults={"auth_enabled"=true},
     *     name=ShipmentController::ROUTE_NAME_CREATE,
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
        $context = new Context(new SystemSource());

        $orderId = $request->get(self::REQUEST_KEY_ORDER_ID);
        $orderVersionId = $request->get(self::REQUEST_KEY_ORDER_VERSION_ID);
        $shippingOptionId = $request->get(self::REQUEST_KEY_SHIPPING_OPTION_ID);

        $existingShipmentEntity = $this->shipmentService->getShipmentByShippingOptionId(
            $shippingOptionId,
            $context
        );

        if ($existingShipmentEntity !== null) {
            return new JsonResponse([
                self::RESPONSE_KEY_SUCCESS => true,
                self::RESPONSE_KEY_SHIPMENT => $existingShipmentEntity
            ]);
        }

        if ((string)$orderId === '' || (string)$orderVersionId === '' || (string)$shippingOptionId === '') {
            return new JsonResponse([
                self::RESPONSE_KEY_SUCCESS => false,
                self::RESPONSE_KEY_ERROR => sprintf(
                    'Request is missing a valid %s, %s or %s',
                    self::REQUEST_KEY_ORDER_ID,
                    self::REQUEST_KEY_ORDER_VERSION_ID,
                    self::REQUEST_KEY_SHIPPING_OPTION_ID
                )
            ]);
        }

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

        $shipment = [
            ShipmentEntity::FIELD_SHIPPING_OPTION => [
                ShipmentEntity::FIELD_ID => $shippingOptionId,
            ],
            ShipmentEntity::FIELD_ORDER => [
                ShipmentEntity::FIELD_ID => $orderId,
                ShipmentEntity::FIELD_VERSION_ID => $orderVersionId,
            ],
        ];

        if ((string)$request->get(self::REQUEST_KEY_CONSIGNMENT_ID) !== '') {
            $shipment[ShipmentEntity::FIELD_CONSIGNMENT_ID] = $request->get(self::REQUEST_KEY_CONSIGNMENT_ID);
        }

        if ((string)$request->get(self::REQUEST_KEY_LABEL_URL) !== '') {
            $shipment[ShipmentEntity::FIELD_LABEL_URL] = $request->get(self::REQUEST_KEY_LABEL_URL);
        }

        if ((float)$request->get(self::REQUEST_KEY_INSURED_AMOUNT) > 0.0) {
            $shipment[ShipmentEntity::FIELD_INSURED_AMOUNT] = $request->get(self::REQUEST_KEY_INSURED_AMOUNT);
        }

        $shipmentEntity = $this->shipmentService->createOrUpdateShipment(
            $shipment,
            $context
        );

        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => $shipmentEntity !== null,
            self::RESPONSE_KEY_SHIPMENT => $shipmentEntity
        ]);
    }
}
