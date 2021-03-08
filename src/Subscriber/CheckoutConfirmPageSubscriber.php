<?php declare(strict_types = 1);

namespace Kiener\KienerMyParcel\Subscriber;

use Kiener\KienerMyParcel\Service\ShippingMethod\ShippingMethodService;
use Kiener\KienerMyParcel\Setting\MyParcelSettingStruct;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    /**
     * @var ShippingMethodService
     */
    private $shippingMethodService;

    /**
     * @var SystemConfigService
     */
    private $configService;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * Creates a new instance of the checkout confirm page subscriber.
     *
     * @param ShippingMethodService $shippingMethodService
     * @param SystemConfigService       $configService
     * @param CartService       $cartService
     */
    public function __construct(
        ShippingMethodService $shippingMethodService,
        SystemConfigService $configService,
        CartService $cartService
    )
    {
        $this->shippingMethodService = $shippingMethodService;
        $this->configService = $configService;
        $this->cartService = $cartService;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => [
                ['addMyParcelDataToPage', 500]
            ]
        ];
    }

    /**
     * Update the shipping costs displayed based on the MyParcel options selected
     *
     * @param PageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    public function updateShippingCosts($args): void
    {
        //check if the current selected option is a myparcel option
        $shippingMethod = $this->shippingMethodService->getShippingMethodByShopwareShippingMethodId(
            $args->getSalesChannelContext()->getShippingMethod()->getId(),
            new Context(new SystemSource())
        );
//dd($args->getPage()->getCart()->getDeliveries());
        if($shippingMethod) {
            $cart = $args->getPage()->getCart();
            foreach($cart->getDeliveries() as $delivery){
                $currentCosts = $delivery->getShippingCosts();

                if(isset($_COOKIE['myparcel-cookie-key'])){
                    $cookie_data = explode('_', $_COOKIE['myparcel-cookie-key']);

                    $deliveryType = $cookie_data[2];
                }else{
                    $deliveryType = $this->configService->get('KienerMyParcel.config.myParcelDefaultDeliveryWindow');
                }

                $raise = '0';

                if($deliveryType == '1') {
                    $raise = $this->configService->get('KienerMyParcel.config.costsDelivery1');
                }
                if($deliveryType == '3') {
                    $raise = $this->configService->get('KienerMyParcel.config.costsDelivery3');
                }
                if($deliveryType == '2'){
                    continue;
                }

                $current = $currentCosts->getUnitPrice();

                $new = (float)bcadd((string)$current, (string)$raise);

                $newCalculatedPrice = new CalculatedPrice(
                    $new,
                    $new,
                    $currentCosts->getCalculatedTaxes(),
                    $currentCosts->getTaxRules()
                );

                $delivery->setShippingCosts($newCalculatedPrice);

            }

            //dump($args->getPage()->getCart());

           $cart = $this->cartService->recalculate($cart, $args->getSalesChannelContext());

            //dd($cart);
        }

    }

    /**
     * Adds an array of MyParcel shipping method ids to the checkout page.
     *
     * @param PageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    public function addMyParcelDataToPage($args): void
    {

        $data = [
            'myparcel_shipping_method_ids' => $this->shippingMethodService->getMyParcelShippingMethodIds(
                $args->getContext()
            ),
        ];

        if(isset($_COOKIE['myparcel-cookie-key'])){
            $cookie_data = explode('_', $_COOKIE['myparcel-cookie-key']);

            $data['myparcel_values'] = [
                'shippingMethodId' => $cookie_data[0],
                'deliveryDate'=> $cookie_data[1],
                'deliveryType'=> $cookie_data[2],
                'requiresSignature'=> $cookie_data[3],
                'onlyRecipient'=> $cookie_data[4]
            ];
        }else{
            $data['myparcel_values'] = [
                'shippingMethodId' => $this->configService->get('KienerMyParcel.config.myParcelDefaultMethod'),
                'deliveryDate'=> \date('Y-m-d', strtotime("+1 day")),
                'deliveryType'=> $this->configService->get('KienerMyParcel.config.myParcelDefaultDeliveryWindow'),
                'requiresSignature'=> $this->configService->get('KienerMyParcel.config.myParcelDefaultSignature'),
                'onlyRecipient'=> $this->configService->get('KienerMyParcel.config.myParcelDefaultOnlyRecipient')
            ];
        }
        $args->getPage()->assign($data);
    }
}
