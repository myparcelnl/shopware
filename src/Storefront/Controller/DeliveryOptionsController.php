<?php

namespace Kiener\KienerMyParcel\Storefront\Controller;

use Kiener\KienerMyParcel\Helper\AddressHelper;
use Kiener\KienerMyParcel\Service\ShippingMethod\ShippingMethodService;
use Shopware\Core\Framework\Context;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

use Shopware\Core\Defaults;

class DeliveryOptionsController extends StorefrontController
{
    /**
     * @var ShippingMethodService
     */
    private $shippingMethodService;

    /** @var SystemConfigService */
    private $configService;

    public const ROUTE_NAME_GET_DELIVERY_OPTIONS = 'myparcel.delivery_options';
    private const RESPONSE_KEY_SUCCESS = 'success';
    private const RESPONSE_KEY_ERROR = 'error';
    private const RESPONSE_KEY_CODE = 'code';
    private const RESPONSE_KEY_DELIVERY_OPTIONS = 'delivery_options';

    public function __construct(
        LoggerInterface $logger,
        ShippingMethodService $shippingMethodService,
        SystemConfigService $configService
    )
    {
        $this->shippingMethodService = $shippingMethodService;
        $this->configService = $configService;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route(
     *     "/myparcel/delivery_options",
     *     name=DeliveryOptionsController::ROUTE_NAME_GET_DELIVERY_OPTIONS,
     *     methods={"POST|GET"},
     *     defaults={"csrf_protected"=false, "XmlHttpRequest"=true}
     *     )
     *
     * @return Response
     * @throws Exception
     */
    public function getDeliveryOptions(Request $request, SalesChannelContext $salesChannelContext, Context $context): Response
    {

        /** @var string $carrier_id */
        $carrier_id = (($request->get('method') != null) ? $request->get('method') : $salesChannelContext->getShippingMethod()->getId());

        /** @var string $cc */
        $cc = $salesChannelContext->getShippingLocation()->getCountry()->getIso();

        /** @var string $postal_code */
        $postal_code = $salesChannelContext->getShippingLocation()->getAddress()->getZipcode();

        $parsedAddress = AddressHelper::parseAddress($salesChannelContext->getShippingLocation()->getAddress());

        /** @var string $number */
        $number = $parsedAddress['houseNumber'];

        /** @var ShippingMethodEntity $carrier */
        $carrier = $this->shippingMethodService->getShippingMethodByShopwareShippingMethodId($carrier_id, $context);

        if(!$carrier){
            return new JsonResponse([
                self::RESPONSE_KEY_SUCCESS => false,
                self::RESPONSE_KEY_ERROR => 'No MyParcel carrier for this Shopware carrier',
                self::RESPONSE_KEY_CODE => '422'
            ]);
        }

        $response = file_get_contents('https://api.myparcel.nl/delivery_options?platform=myparcel&carrier='.$carrier->getCarrierName().'&cc='.$cc.'&number='.$number.'&postal_code='.$postal_code);

        if(!$response || empty(json_decode($response)->data->delivery)){

            if(json_decode($response)->errors){
                return new JsonResponse([
                    self::RESPONSE_KEY_SUCCESS => false,
                    self::RESPONSE_KEY_ERROR => json_decode($response, true)['errors'],
                    self::RESPONSE_KEY_CODE => '503',
                ]);
            } else {
                return new JsonResponse([
                    self::RESPONSE_KEY_SUCCESS => false,
                    self::RESPONSE_KEY_ERROR => 'Failed to receive options from MyParcel',
                    self::RESPONSE_KEY_CODE => '503',
                ]);
            }
        }

        $viewData = [
            'options' => json_decode($response, true)['data']['delivery'],
            'carrier_id' => $salesChannelContext->getShippingMethod()->getId(),
            'salesContext' => $salesChannelContext,
            'context' => $context,
            'config' => $this->configService->get('KienerMyParcel.config')
        ];

        return $this->renderStorefront('@Storefront/storefront/component/checkout/carrier-options.html.twig', $viewData);


    }
}
