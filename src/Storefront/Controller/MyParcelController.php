<?php
/**
 * @noinspection PhpUnused
 * @noinspection PhpUndefinedClassInspection
 */
namespace Kiener\KienerMyParcel\Storefront\Controller;

use Exception;
use Kiener\KienerMyParcel\Service\Consignment\ConsignmentService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MyParcelController extends StorefrontController
{
    public const ROUTE_NAME_GET_CARRIERS = 'api.action.myparcel.carriers';
    public const ROUTE_NAME_GET_PACKAGE_TYPES = 'api.action.myparcel.package_types';

    private const RESPONSE_KEY_SUCCESS = 'success';
    private const RESPONSE_KEY_CARRIERS = 'carriers';
    private const RESPONSE_KEY_PACKAGE_TYPES = 'package_types';

    /**
     * @var ConsignmentService
     */
    private $consignmentService;

    /**
     * MyParcelController constructor.
     *
     * @param ConsignmentService $consignmentService
     */
    public function __construct(
        ConsignmentService $consignmentService
    )
    {
        $this->consignmentService = $consignmentService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/carriers",
     *     defaults={"auth_enabled"=true},
     *     name=MyParcelController::ROUTE_NAME_GET_CARRIERS,
     *     methods={"GET"}
     *     )
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function getCarriers(): JsonResponse
    {
        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => true,
            self::RESPONSE_KEY_CARRIERS => $this->consignmentService->getCarrierIds(),

        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/myparcel/package_types",
     *     defaults={"auth_enabled"=true},
     *     name=MyParcelController::ROUTE_NAME_GET_PACKAGE_TYPES,
     *     methods={"GET"}
     *     )
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function getPackageTypes(): JsonResponse
    {
        return new JsonResponse([
            self::RESPONSE_KEY_SUCCESS => true,
            self::RESPONSE_KEY_PACKAGE_TYPES => $this->consignmentService->getPackageTypes(),

        ]);
    }
}