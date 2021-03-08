<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Cart\Checkout;

use Kiener\KienerMyParcel\Service\ShippingMethod\ShippingMethodService;
use Kiener\KienerMyParcel\Setting\MyParcelSettingStruct;
use MyParcelNL\Sdk\src\Model\Consignment\AbstractConsignment;
use Shopware\Administration\Service\AdminOrderCartService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ShippingCostsCollector implements CartDataCollectorInterface, CartProcessorInterface
{
    private const SHIPPING_COSTS_KEY = '-shipping-costs';
    private const ORIGINAL_PRICE_KEY = '-original-price';

    /**
     * @var AdminOrderCartService
     */
    private $adminOrderCartService;

    /**
     * @var QuantityPriceCalculator
     */
    private $calculator;

    /**
     * @var ShippingMethodService
     */
    private $shippingMethodService;


    public function __construct(
        AdminOrderCartService $adminOrderCartService,
        QuantityPriceCalculator $calculator,
        ShippingMethodService $shippingMethodService
    ) {
        $this->adminOrderCartService = $adminOrderCartService;
        $this->calculator = $calculator;
        $this->shippingMethodService = $shippingMethodService;
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        /** @var array $filtered */
        $filtered = $this->filterShippingCosts($context->getToken(), $data);

        /** @var string $shippingMethodId */
        $shippingMethodId = $context->getSalesChannel()->getShippingMethodId();

        /** @var float $price */
        $price = $this->getAddedDeliveryCosts($shippingMethodId, $context);

        /** @var string $originalPriceKey */
        $originalPriceKey = $this->buildKey($context->getToken() . self::ORIGINAL_PRICE_KEY);

        /** @var string $shippingCostsKey */
        $shippingCostsKey = $this->buildKey($context->getToken() . self::SHIPPING_COSTS_KEY);

        if (
            $price > 0.0
            && !empty($filtered)
            && in_array(
                $shippingMethodId,
                $this->shippingMethodService->getMyParcelShippingMethodIds($context->getContext()),
                true
            )
        ) {
            $originalPrice = $original->getShippingCosts()->getUnitPrice();

            if ($data->has($originalPriceKey)) {
                $originalPrice = $data->get($originalPriceKey);
            }

            // Create a new price definition for the shipping costs
            $definition = new QuantityPriceDefinition(
                $price,
                $original->getShippingCosts()->getTaxRules(),
                $context->getCurrency()->getDecimalPrecision(),
                $original->getShippingCosts()->getQuantity(),
                true
            );

            /** @var CalculatedPrice $newShippingCosts */
            $newShippingCosts = $this->calculator->calculate($definition, $context);

            // Add the new original price to the dataset
            $data->set($originalPriceKey, $originalPrice);

            // Add the new shipping costs to the dataset
            $data->set($shippingCostsKey, $newShippingCosts);
        }
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        /** @var string $shippingCostsKey */
        $shippingCostsKey = $this->buildKey($context->getToken() . self::SHIPPING_COSTS_KEY);

        /** @var CalculatedPrice $shippingCosts */
        $shippingCosts = null;

        /** @var string $shippingMethodId */
        $shippingMethodId = $context->getSalesChannel()->getShippingMethodId();

        if ($data->has($shippingCostsKey)) {
            $shippingCosts = $data->get($shippingCostsKey);
        }

        if (
            $shippingCosts !== null
            && in_array(
                $shippingMethodId,
                $this->shippingMethodService->getMyParcelShippingMethodIds($context->getContext()),
                true
            )
        ) {
            $delivery = $original->getDeliveries()->first();

            // Set shipping costs to the delivery, add the delivery to the
            if ($delivery) {
                $delivery->setShippingCosts($shippingCosts);
                $toCalculate->getDeliveries()->add($delivery);
            }

            // Get the delivery
            $newDelivery = $toCalculate->getDeliveries()->first();

            // Add permission to the admin cart service to skip price recalculation
            $this->adminOrderCartService->addPermission($context->getToken(), DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION);

            if (
                $newDelivery !== null
                && $newDelivery->getShippingCosts()->getTotalPrice() !== $original->getShippingCosts()->getTotalPrice()
            ) {
                $this->adminOrderCartService->updateShippingCosts($shippingCosts, $context);
            }
        }
    }

    private function filterShippingCosts(string $id, CartDataCollection $data)
    {
        $key = $this->buildKey($id);

        if (!$data->has($key)) {
            return [$id];
        }
    }

    private function buildKey(string $id): string
    {
        return 'overwritten-shipping-costs-' . $id;
    }

    private function getAddedDeliveryCosts(string $shippingMethodId, SalesChannelContext $context): float
    {
        /** @var float $price */
        $price = 0.0;

        /** @var array $cookieParts */
        $cookieParts = [];

        /** @var int $deliveryType */
        $deliveryType = null;

        if (isset($_COOKIE['myparcel-cookie-key'])) {
            $cookieParts = explode('_', $_COOKIE['myparcel-cookie-key']);
        }

        if (!empty($cookieParts) && $cookieParts[0] === $shippingMethodId) {
            $deliveryType = (int) $cookieParts[1];
        }

        if ($deliveryType === AbstractConsignment::DELIVERY_TYPE_MORNING) {
            $price = 0;
        }

        if ($deliveryType === AbstractConsignment::DELIVERY_TYPE_EVENING) {
            $price = 0;
        }

        return $price;
    }
}
