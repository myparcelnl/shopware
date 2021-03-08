<?php
/**
 * @noinspection PhpUnused
 * @noinspection PhpUndefinedClassInspection
 */
namespace Kiener\KienerMyParcel\Storefront\Controller;

use Exception;
use Kiener\KienerMyParcel\Service\Consignment\ConsignmentService;
use Kiener\KienerMyParcel\Service\Shipment\ShipmentService;
use MyParcelNL\Sdk\src\Exception\MissingFieldException;
use MyParcelNL\Sdk\src\Helper\TrackTraceUrl;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConsignmentController extends StorefrontController
{
    public const ROUTE_NAME_GET_CARRIERS = 'api.action.myparcel.carriers';
    public const ROUTE_NAME_GET_PACKAGE_TYPES = 'api.action.myparcel.package_types';
    public const ROUTE_NAME_CREATE = 'api.action.myparcel.consignment.create';
    public const ROUTE_NAME_CREATE_CONSIGNMENTS = 'api.action.myparcel.consignment.create_consignments';
    public const ROUTE_NAME_GET_BY_REFERENCE_ID = 'api.action.myparcel.consignment.get_by_reference_id';
    public const ROUTE_NAME_GET_FOR_SHIPPING_OPTION = 'api.action.myparcel.consignment.get_for_shipping_option';
    public const ROUTE_NAME_DOWNLOAD_LABELS = 'api.action.myparcel.consignment.download_labels';
    public const ROUTE_NAME_TRACK_AND_TRACE = 'api.action.myparcel.consignment.track_and_trace';

    private const REQUEST_KEY_ORDERS = 'orders';
    private const REQUEST_KEY_LABEL_POSITIONS = 'label_positions';
    private const REQUEST_KEY_PACKAGE_TYPE = 'package_type';
    private const REQUEST_KEY_NUMBER_OF_LABELS = 'number_of_labels';
    private const REQUEST_KEY_SHIPMENT_ID = 'shipment_id';
    private const REQUEST_KEY_REFERENCE_ID = 'reference_id';
    private const REQUEST_KEY_REFERENCE_IDS = 'reference_ids';
    private const REQUEST_KEY_SHIPPING_OPTION_ID = 'shipping_option_id';

    private const RESPONSE_KEY_CONSIGNMENTS = 'consignments';
    private const RESPONSE_KEY_ERROR = 'error';
    private const RESPONSE_KEY_LABEL_URL = 'labelUrl';
    private const RESPONSE_KEY_TRACK_TRACE_INFO = 'trackTraceInfo';
    private const RESPONSE_KEY_SUCCESS = 'success';

    /**
     * @var ConsignmentService
     */
    private $consignmentService;

    /**
     * @var ShipmentService
     */
    private $shipmentService;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * ConsignmentController constructor.
     *
     * @param ConsignmentService $consignmentService
     * @param ShipmentService    $shipmentService
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(
        ConsignmentService $consignmentService,
        ShipmentService $shipmentService,
        SystemConfigService $systemConfigService
    )
    {
        $this->consignmentService = $consignmentService;
        $this->shipmentService = $shipmentService;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/consignment/get-for-shipping-option",
     *     defaults={"auth_enabled"=true},
     *     name=ConsignmentController::ROUTE_NAME_GET_FOR_SHIPPING_OPTION,
     *     methods={"POST"}
     *     )
     *
     * @return JsonResponse
     */
    public function getForShippingOption(Request $request): JsonResponse
    {
        $shippingOptionId = $request->get(self::REQUEST_KEY_SHIPPING_OPTION_ID);
        $consignments = null;

        if ((string) $shippingOptionId === '') {
            return new JsonResponse([
                self::RESPONSE_KEY_SUCCESS => false,
                self::RESPONSE_KEY_ERROR => sprintf(
                    'Request is missing a valid %s',
                    self::REQUEST_KEY_SHIPPING_OPTION_ID
                )
            ]);
        }

        if ((string) $shippingOptionId !== '') {
            $consignments = $this->shipmentService->getShipmentsByShippingOptionId(
                $shippingOptionId,
                new Context(new SystemSource())
            );
        }

        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => true,
            self::RESPONSE_KEY_CONSIGNMENTS => $consignments,
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/consignment/create-consignments",
     *     defaults={"auth_enabled"=true},
     *     name=ConsignmentController::ROUTE_NAME_CREATE_CONSIGNMENTS,
     *     methods={"POST"}
     *     )
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function createConsignments(Request $request): JsonResponse
    {
        /**
         * @todo Per consignment een Shipment maken zodat dit niet in Javascript hoeft
         * Voor Shipment wordt meegegeven: Order_id (al present), Order_version_id (al present) en ShippingOption_ID (required) [Optioneel: InsuredAmount]
         */

        $context = new Context(new SystemSource());

        if (
            $request->get(self::REQUEST_KEY_ORDERS) === null
            || !is_array($request->get(self::REQUEST_KEY_ORDERS))
            || empty($request->get(self::REQUEST_KEY_ORDERS))
        ) {
            return new JsonResponse([
                self::RESPONSE_KEY_SUCCESS => false,
                self::RESPONSE_KEY_ERROR => sprintf('Missing valid %s array with ids as parameter', self::REQUEST_KEY_ORDERS)
            ]);
        }

        if (
            $request->get(self::REQUEST_KEY_LABEL_POSITIONS) !== null
            && is_array($request->get(self::REQUEST_KEY_LABEL_POSITIONS))
            && !empty($request->get(self::REQUEST_KEY_LABEL_POSITIONS))
        ) {
            $labelPositions = $request->get(self::REQUEST_KEY_LABEL_POSITIONS);
        }else{
            if($this->systemConfigService->get('KienerMyParcel.config.myParcelDefaultLabelFormat') == 'A6'){
                $labelPositions = null;
            }else{
                $labelPositions = 1;
            }
        }

        if (
            $request->get(self::REQUEST_KEY_PACKAGE_TYPE) !== null
            && is_array($request->get(self::REQUEST_KEY_PACKAGE_TYPE))
            && !empty($request->get(self::REQUEST_KEY_PACKAGE_TYPE))
        ) {
            $packageType = $request->get(self::REQUEST_KEY_PACKAGE_TYPE);
        }

        if (
            $request->get(self::REQUEST_KEY_NUMBER_OF_LABELS) !== null
            && !empty($request->get(self::REQUEST_KEY_NUMBER_OF_LABELS))
        ) {
            $numberOfLabels = $request->get(self::REQUEST_KEY_NUMBER_OF_LABELS);
        }

        $consignments = $this->consignmentService->createConsignments(
            $context,
            $request->get(self::REQUEST_KEY_ORDERS),
            $labelPositions ?? null,
            $packageType ?? null,
            $numberOfLabels ?? null
        );

        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => $consignments !== null,
            self::RESPONSE_KEY_LABEL_URL => $consignments->getLinkOfLabels(),
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/consignment/download-labels",
     *     defaults={"auth_enabled"=true},
     *     name=ConsignmentController::ROUTE_NAME_DOWNLOAD_LABELS,
     *     methods={"POST"}
     *     )
     *
     * @return JsonResponse
     * @throws MissingFieldException
     */
    public function downloadLabels(Request $request): JsonResponse
    {
        if (
            $request->get(self::REQUEST_KEY_LABEL_POSITIONS) !== null
            && is_array($request->get(self::REQUEST_KEY_LABEL_POSITIONS))
            && !empty($request->get(self::REQUEST_KEY_LABEL_POSITIONS))
        ) {
            $labelPositions = $request->get(self::REQUEST_KEY_LABEL_POSITIONS);
        }

        if (
            $request->get(self::REQUEST_KEY_REFERENCE_IDS) !== null
            && is_array($request->get(self::REQUEST_KEY_REFERENCE_IDS))
            && !empty($request->get(self::REQUEST_KEY_REFERENCE_IDS))
        ) {
            $referenceIds = $request->get(self::REQUEST_KEY_REFERENCE_IDS);
        }

        if (
            isset($referenceIds)
            && is_array($referenceIds)
            && !empty($referenceIds)
        ) {
            $consignments = $this->consignmentService->findManyByReferenceId($referenceIds);
        }

        if (isset($consignments)) {
            try {
                if (
                    isset($labelPositions)
                    && is_array($labelPositions)
                    && !empty($labelPositions)
                ) {
                    $consignments->setLinkOfLabels(count($labelPositions) === 1 ? $labelPositions[0] : $labelPositions);
                } else {
                    $consignments->setLinkOfLabels(false);
                }
            } catch (Exception $e) {
                var_dump($e->getMessage());
            }
        }

        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => isset($consignments),
            self::RESPONSE_KEY_LABEL_URL => isset($consignments) ? $consignments->getLinkOfLabels() : null,
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/consignment/track-and-trace",
     *     defaults={"auth_enabled"=true},
     *     name=ConsignmentController::ROUTE_NAME_TRACK_AND_TRACE,
     *     methods={"POST"}
     *     )
     *
     * @return JsonResponse
     * @throws MissingFieldException
     */
    public function trackAndTrace(Request $request): JsonResponse
    {
        if (
            $request->get(self::REQUEST_KEY_REFERENCE_ID) !== null
        ) {
            $referenceId = $request->get(self::REQUEST_KEY_REFERENCE_ID);
        }

        if (isset($referenceId)) {
            /** @var array $consignment */
            $consignment = $this->consignmentService->findByReferenceId($referenceId);
        }

        if (
            isset($consignment)
            && is_array($consignment)
            && !empty($consignment)
        ) {
            $trackTraceInfo = [
                'barcode' => $consignment[0]->getBarcode(),
                'url' => TrackTraceUrl::create(
                    $consignment[0]->getBarcode(),
                    $consignment[0]->getPostalCode(),
                    $consignment[0]->getCountry()
                ),
                //'consignment' => $consignment,
            ];
        }

        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => isset($consignment),
            self::RESPONSE_KEY_TRACK_TRACE_INFO => $trackTraceInfo ?? null,
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/consignment/get-by-reference-id/{$referenceId}",
     *     defaults={"auth_enabled"=true},
     *     name=ConsignmentController::ROUTE_NAME_GET_BY_REFERENCE_ID,
     *     methods={"POST"}
     *     )
     *
     * @param string $referenceId
     *
     * @return JsonResponse
     * @throws MissingFieldException
     */
    public function getByReferenceId(string $referenceId): JsonResponse
    {
        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => true,
            'consignments' => $this->consignmentService->findByReferenceId($referenceId),
        ]);
    }
}
