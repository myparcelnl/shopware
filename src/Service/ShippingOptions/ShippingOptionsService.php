<?php

namespace Kiener\KienerMyParcel\Service\ShippingOptions;

use Kiener\KienerMyParcel\Core\Content\ShippingOption\ShippingOptionEntity;
use MyParcelNL\Sdk\src\Model\Consignment\AbstractConsignment;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ShippingOptionsService
{
    private const FIELD_NAME = 'name';
    private const FIELD_COSTS = 'costs';
    private const MORNING_TYPE = '1';
    private const STANDARD_TYPE = '2';
    private const EVENING_TYPE = '3';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityRepositoryInterface
     */
    private $shippingOptionsRepository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * ShippingOptionsService constructor.
     *
     * @param LoggerInterface           $logger
     * @param EntityRepositoryInterface $shippingOptionsRepository
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(
        LoggerInterface $logger,
        EntityRepositoryInterface $shippingOptionsRepository,
        SystemConfigService $systemConfigService
    )
    {
        $this->logger = $logger;
        $this->shippingOptionsRepository = $shippingOptionsRepository;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @param array   $params
     * @param Context $context
     *
     * @return ShippingOptionEntity|null
     */
    public function createOrUpdateShippingOptions(array $params, Context $context): ?ShippingOptionEntity
    {
        // Create a new shipment entity if no id is present
        if (!isset($params[ShippingOptionEntity::FIELD_ID])) {
            $params[ShippingOptionEntity::FIELD_ID] = Uuid::randomHex();
        }

        // Upsert the data in the database
        $event = $this->shippingOptionsRepository->upsert([$params], $context);

        // Check for errors
        if (!empty($event->getErrors())) {
            $this->logger->error(
                implode(', ', $event->getErrors()),
                $event->getErrors()
            );

            return null;
        }

        return $this->getShippingOptions($params[ShippingOptionEntity::FIELD_ID], $context);
    }

    /**
     * @param string  $id
     * @param Context $context
     *
     * @return ShippingOptionEntity|null
     */
    public function getShippingOptions(string $id, Context $context): ?ShippingOptionEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('order');
        //$criteria->addAssociation('consignments');

        return $this->shippingOptionsRepository->search($criteria, $context)->get($id);
    }

    /**
     * @param string  $id
     * @param Context $context
     *
     * @return array
     */
    public function getAllShippingOptions(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('order');
        $criteria->addAssociation('consignments');

        return $this->shippingOptionsRepository->search($criteria, $context)->getVars();
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context     $context
     *
     * @return ShippingOptionEntity|null
     */
    public function getShippingOptionsForOrder(OrderEntity $orderEntity, Context $context): ?ShippingOptionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderEntity->getId()))
            ->addFilter(new EqualsFilter('orderVersionId', $orderEntity->getVersionId()));

        return $this->shippingOptionsRepository->search($criteria, $context)->first();
    }

    /**
     * @return array
     */
    public function getDeliveryTypes(): array
    {
        return [
            AbstractConsignment::DELIVERY_TYPE_MORNING => [
                self::FIELD_NAME => AbstractConsignment::DELIVERY_TYPE_MORNING_NAME,
                self::FIELD_COSTS => 0,
            ],
            AbstractConsignment::DELIVERY_TYPE_STANDARD => [
                self::FIELD_NAME => AbstractConsignment::DELIVERY_TYPE_STANDARD_NAME,
                self::FIELD_COSTS => 0,
            ],
            AbstractConsignment::DELIVERY_TYPE_EVENING => [
                self::FIELD_NAME => AbstractConsignment::DELIVERY_TYPE_EVENING_NAME,
                self::FIELD_COSTS => 0,
            ],
            AbstractConsignment::DELIVERY_TYPE_PICKUP => [
                self::FIELD_NAME => AbstractConsignment::DELIVERY_TYPE_PICKUP_NAME,
                self::FIELD_COSTS => 0,
            ],
        ];
    }

    /**
     * @return float
     */
    public function getShippingOptionsRaisePrice(): float
    {
        if(isset($_COOKIE['myparcel-cookie-key'])){
            $cookie_data = explode('_', $_COOKIE['myparcel-cookie-key']);

            $deliveryType = $cookie_data[2];
        }else{
            $deliveryType = $this->systemConfigService->get('KienerMyParcel.config.myParcelDefaultDeliveryWindow');
        }

        $raise = 0;

        if($deliveryType == self::MORNING_TYPE) {
            $raise = $this->systemConfigService->get('KienerMyParcel.config.costsDelivery1');
        }
        if($deliveryType == self::EVENING_TYPE) {
            $raise = $this->systemConfigService->get('KienerMyParcel.config.costsDelivery3');
        }

        return $raise;
    }
}
