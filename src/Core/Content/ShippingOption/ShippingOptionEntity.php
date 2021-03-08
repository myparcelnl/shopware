<?php

namespace Kiener\KienerMyParcel\Core\Content\ShippingOption;

use Kiener\KienerMyParcel\Core\Content\Shipment\ShipmentEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ShippingOptionEntity extends Entity //NOSONAR
{
    use EntityIdTrait;

    public const FIELD_ID = 'id';
    public const FIELD_ORDER = 'order';
    public const FIELD_ORDER_ID = 'orderId';
    public const FIELD_ORDER_VERSION_ID = 'orderVersionId';
    public const FIELD_VERSION_ID = 'versionId';
    public const FIELD_SHIPMENT = 'shipment';
    public const FIELD_SHIPMENT_ID = 'shipmentId';
    public const FIELD_CARRIER_ID = 'carrierId';
    public const FIELD_PACKAGE_TYPE = 'packageType';
    public const FIELD_DELIVERY_DATE = 'deliveryDate';
    public const FIELD_DELIVERY_TYPE = 'deliveryType';
    public const FIELD_REQUIRES_AGE_CHECK = 'requiresAgeCheck';
    public const FIELD_REQUIRES_SIGNATURE = 'requiresSignature';
    public const FIELD_ONLY_RECIPIENT = 'onlyRecipient';
    public const FIELD_RETURN_IF_NOT_HOME = 'returnIfNotHome';
    public const FIELD_LARGE_FORMAT = 'largeFormat';

    /**
     * @var OrderEntity
     */
    protected $order;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $orderVersionId;

    /**
     * @var int
     */
    protected $carrierId;

    /**
     * @var EntityCollection|null
     */
    protected $consignments;

    /**
     * @var int
     */
    protected $packageType;

    /**
     * @var date
     */
    protected $deliveryDate;

    /**
     * @var int
     */
    protected $deliveryType;

    /**
     * @var bool
     */
    protected $requiresAgeCheck;

    /**
     * @var bool
     */
    protected $requiresSignature;

    /**
     * @var bool
     */
    protected $onlyRecipient;

    /**
     * @var bool
     */
    protected $returnIfNotHome;

    /**
     * @var bool
     */
    protected $largeFormat;

    /**
     * @return OrderEntity
     */
    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    /**
     * @param OrderEntity $order
     *
     * @return self
     */
    public function setOrder(OrderEntity $order): self
    {
        $this->order = $order;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * @param string $orderId
     *
     * @return self
     */
    public function setOrderId(string $orderId): self
    {
        $this->orderId = $orderId;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    /**
     * @param string $orderVersionId
     *
     * @return self
     */
    public function setOrderVersionId(string $orderVersionId): self
    {
        $this->orderVersionId = $orderVersionId;
        return $this;
    }

    /**
     * @return int
     */
    public function getCarrierId(): int
    {
        return $this->carrierId;
    }

    /**
     * @param int $carrierId
     *
     * @return ShippingOptionEntity
     */
    public function setCarrierId(int $carrierId): ShippingOptionEntity
    {
        $this->carrierId = $carrierId;
        return $this;
    }

    /**
     * @return EntityCollection|null
     */
    public function getConsignments(): ?EntityCollection
    {
        return $this->consignments;
    }

    /**
     * @param EntityCollection|null $consignments
     *
     * @return self
     */
    public function setConsignments(EntityCollection $consignments): self
    {
        $this->consignments = $consignments;
        return $this;
    }

    /**
     * @return int
     */
    public function getPackageType(): int
    {
        return $this->packageType;
    }

    /**
     * @param int $packageType
     *
     * @return ShippingOptionEntity
     */
    public function setPackageType(int $packageType): ShippingOptionEntity
    {
        $this->packageType = $packageType;
        return $this;
    }

    /**
     * @return int
     */
    public function getDeliveryType(): int
    {
        return $this->deliveryType;
    }

    /**
     * @param int $deliveryType
     *
     * @return ShippingOptionEntity
     */
    public function setDeliveryType(int $deliveryType): ShippingOptionEntity
    {
        $this->deliveryType = $deliveryType;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRequiresAgeCheck(): bool
    {
        return $this->requiresAgeCheck;
    }

    /**
     * @param bool $requiresAgeCheck
     *
     * @return ShippingOptionEntity
     */
    public function setRequiresAgeCheck(bool $requiresAgeCheck): ShippingOptionEntity
    {
        $this->requiresAgeCheck = $requiresAgeCheck;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRequiresSignature(): bool
    {
        return $this->requiresSignature;
    }

    /**
     * @param bool $requiresSignature
     *
     * @return ShippingOptionEntity
     */
    public function setRequiresSignature(bool $requiresSignature): ShippingOptionEntity
    {
        $this->requiresSignature = $requiresSignature;
        return $this;
    }

    /**
     * @return bool
     */
    public function getOnlyRecipient(): bool
    {
        return $this->onlyRecipient;
    }

    /**
     * @param bool $onlyRecipient
     *
     * @return ShippingOptionEntity
     */
    public function setOnlyRecipient(bool $onlyRecipient): ShippingOptionEntity
    {
        $this->onlyRecipient = $onlyRecipient;
        return $this;
    }

    /**
     * @return bool
     */
    public function getReturnIfNotHome(): bool
    {
        return $this->returnIfNotHome;
    }

    /**
     * @param bool $returnIfNotHome
     *
     * @return ShippingOptionEntity
     */
    public function setReturnIfNotHome(bool $returnIfNotHome): ShippingOptionEntity
    {
        $this->returnIfNotHome = $returnIfNotHome;
        return $this;
    }

    /**
     * @return bool
     */
    public function getLargeFormat(): bool
    {
        return $this->largeFormat;
    }

    /**
     * @param bool $largeFormat
     *
     * @return self
     */
    public function setLargeFormat(bool $largeFormat): self
    {
        $this->largeFormat = $largeFormat;
        return $this;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getDeliveryDate(): \DateTimeImmutable
    {
        return $this->deliveryDate;
    }

    /**
     * @param date $deliveryDate
     */
    public function setDeliveryDate(date $deliveryDate): self
    {
        $this->deliveryDate = $deliveryDate;
    }
}
