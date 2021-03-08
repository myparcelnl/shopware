<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Service\ShippingMethod;

use Kiener\KienerMyParcel\Core\Content\ShippingMethod\ShippingMethodEntity;
use Kiener\KienerMyParcel\Service\Consignment\ConsignmentService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity as ShopwareShippingMethodEntity;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

class ShippingMethodService
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ConsignmentService
     */
    private $consignmentService;

    /**
     * @var EntityRepositoryInterface
     */
    private $deliveryTimeRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $myParcelShippingMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleConditionRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $shopwareShippingMethodRepository;

    public function __construct(
        LoggerInterface $logger,
        ConsignmentService $consignmentService,
        EntityRepositoryInterface $deliveryTimeRepository,
        EntityRepositoryInterface $myParcelShippingMethodRepository,
        EntityRepositoryInterface $ruleConditionRepository,
        EntityRepositoryInterface $shopwareShippingMethodRepository
    )
    {
        $this->logger = $logger;
        $this->consignmentService = $consignmentService;
        $this->deliveryTimeRepository = $deliveryTimeRepository;
        $this->myParcelShippingMethodRepository = $myParcelShippingMethodRepository;
        $this->ruleConditionRepository = $ruleConditionRepository;
        $this->shopwareShippingMethodRepository = $shopwareShippingMethodRepository;
    }

    public function createShippingMethods(Context $context): void
    {
        /** @var array $carriers */
        $carriers = $this->consignmentService->getCarrierIds();

        if (
            is_array($carriers)
            && !empty($carriers)
        ) {
            foreach ($carriers as $carrierName => $carrierId) {
                // Create a Shopware shipping method
                $shippingMethodId = $this->createShopwareShippingMethod(
                    $carrierId,
                    $carrierName,
                    $context
                );

                // Connect the shipping method to a MyParcel carrier
                if ($shippingMethodId !== null) {
                    $this->createMyParcelShippingMethod(
                        $shippingMethodId,
                        $carrierId,
                        $carrierName,
                        $context
                    );

                    // @todo Handle errors, so the merchant knows if any issues occur
                }
            }
        }
    }

    /**
     * Returns whether a shipping method is a MyParcel shipping method.
     *
     * @param ShopwareShippingMethodEntity $shippingMethod
     * @param Context                      $context
     *
     * @return bool
     */
    public function isMyParcelShippingMethod(ShopwareShippingMethodEntity $shippingMethod, Context $context): bool
    {
        return $this->getShippingMethodByShopwareShippingMethodId($shippingMethod->getId(), $context) !== null;
    }

    /**
     * Returns MyParcel shipping methods ids.
     *
     * @param Context $context
     *
     * @return array
     */
    public function getMyParcelShippingMethodIds(Context $context): array
    {
        $shippingMethodIds = [];
        $criteria = new Criteria();
        $shippingMethods = $this->myParcelShippingMethodRepository->search($criteria, $context);

        /** @var ShippingMethodEntity $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethodIds[] = $shippingMethod->getShippingMethodId();
        }

        return $shippingMethodIds;
    }

    /**
     * Returns a shipping method by carrier id.
     *
     * @param string  $shopwareShippingMethodId
     * @param Context $context
     *
     * @return ShippingMethodEntity|null
     */
    public function getShippingMethodByShopwareShippingMethodId(string $shopwareShippingMethodId, Context $context): ?ShippingMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shippingMethodId', $shopwareShippingMethodId));
        $criteria->addAssociation('shipping_method');

        $shippingMethods = $this->myParcelShippingMethodRepository->search($criteria, $context);

        return $shippingMethods->first();
    }

    /**
     * Returns a shipping method by carrier id.
     *
     * @param int     $carrierId
     * @param Context $context
     *
     * @return ShippingMethodEntity|null
     */
    public function getShippingMethodByCarrierId(int $carrierId, Context $context): ?ShippingMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('carrierId', $carrierId));
        $criteria->addAssociation('shipping_method');

        $shippingMethods = $this->myParcelShippingMethodRepository->search($criteria, $context);

        return $shippingMethods->first();
    }

    /**
     * Creates a shipping method for MyParcel.
     *
     * @param string  $shippingMethodId
     * @param int     $carrierId
     * @param string  $carrierName
     * @param Context $context
     *
     * @return bool
     */
    private function createMyParcelShippingMethod(
        string $shippingMethodId,
        int $carrierId,
        string $carrierName,
        Context $context
    ): bool
    {
        /** @var string $id */
        $id = Uuid::randomHex();

        /** @var ShippingMethodEntity|null $existingShippingMethod */
        $existingShippingMethod = $this->getShippingMethodByCarrierId($carrierId, $context);

        if ($existingShippingMethod !== null) {
            $id = $existingShippingMethod->getId();
        }

        /** @var EntityWrittenEvent $event */
        $event = $this->myParcelShippingMethodRepository->upsert([
            [
                'id' => $id,
                'carrierId' => $carrierId,
                'carrierName' => $carrierName,
                'shippingMethod' => [
                    'id' => $shippingMethodId,
                ],
            ],
        ], $context);

        if (!empty($event->getErrors())) {
            $this->logger->error(
                implode(',', $event->getErrors()),
                $event->getErrors()
            );

            return false;
        }

        return true;
    }

    /**
     * Creates a shipping method in Shopware.
     *
     * @param int     $carrierId
     * @param string  $carrierName
     * @param Context $context
     *
     * @return string|null
     */
    private function createShopwareShippingMethod(
        int $carrierId,
        string $carrierName,
        Context $context
    ): ?string
    {
        /** @var string $id */
        $id = Uuid::randomHex();

        /** @var ShippingMethodEntity|null $existingShippingMethod */
        $existingShippingMethod = $this->getShippingMethodByCarrierId($carrierId, $context);

        if ($existingShippingMethod !== null) {
            return $existingShippingMethod->getShippingMethodId();
        }

        // Build an array of availability rule data
        $availabilityRuleData = [
            'name' => 'Always valid (Default)',
            'priority' => 100,
            'type' => (new AlwaysValidRule())->getName(),
        ];

        // Get the id of the default rule
        $rule = $this->getAlwaysValidRule($context);

        if ($rule !== null) {
            $availabilityRuleData = [
                'id' => $rule->getId(),
            ];
        }

        // Build an array of delivery time data
        $deliveryTimeData = [
            'min' => 1,
            'max' => 3,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            'name' => '1 - 3 days',
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => '1 - 3 days',
                ],
            ],
        ];

        $existingDeliveryTime = $this->getExistingDeliveryTime($context);

        if ($existingDeliveryTime !== null) {
            $deliveryTimeData = [
                'id' => $existingDeliveryTime->getId(),
            ];
        }

        // Create or update the shipping method
        $event = $this->shopwareShippingMethodRepository->upsert([
            [
                'id' => $id,
                'name' => $carrierName,
                'active' => false,
                'availabilityRule' => $availabilityRuleData,
                'deliveryTime' => $deliveryTimeData,
                'prices' => [
                    [
                        'calculation' => 1,
                        'currencyId' => $context->getCurrencyId(),
                        'price' => 0.0,
                        'quantityStart' => 1,
                    ]
                ],
            ],
        ], $context);

        if (!empty($event->getErrors())) {
            $this->logger->error(
                implode(', ', $event->getErrors()),
                $event->getErrors()
            );

            return null;
        }

        return $id;
    }

    /**
     * Returns the always valid rule.
     *
     * @param Context $context
     *
     * @return RuleEntity|null
     */
    private function getAlwaysValidRule(Context $context): ?RuleEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type', (new AlwaysValidRule())->getName()));
        $criteria->addAssociation('rule');

        /** @var RuleConditionEntity|null $ruleCondition */
        $ruleCondition = $this->ruleConditionRepository->search($criteria, $context)->first();

        if (
            $ruleCondition !== null
            && $ruleCondition->getRule() !== null
        ) {
            return $ruleCondition->getRule();
        }

        return null;
    }

    /**
     * Returns an existing delivery time.
     *
     * @param Context $context
     *
     * @return DeliveryTimeEntity|null
     */
    private function getExistingDeliveryTime(Context $context): ?DeliveryTimeEntity
    {
        return $this->deliveryTimeRepository->search(new Criteria(), $context)->first();
    }
}