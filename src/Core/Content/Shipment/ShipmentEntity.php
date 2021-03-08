<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Core\Content\Shipment;

use Kiener\KienerMyParcel\Core\Content\ShippingOption\ShippingOptionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ShipmentEntity extends Entity
{
    use EntityIdTrait;

    public const FIELD_ID = 'id';
    public const FIELD_CONSIGNMENT_REFERENCE = 'consignmentReference';
    public const FIELD_SHIPPING_OPTION = 'shippingOption';
    public const FIELD_SHIPPING_OPTION_ID = 'shippingOptionId';
    public const FIELD_ORDER = 'order';
    public const FIELD_ORDER_ID = 'orderId';
    public const FIELD_ORDER_VERSION_ID = 'orderVersionId';
    public const FIELD_VERSION_ID = 'versionId';
    public const FIELD_LABEL_URL = 'labelUrl';
    public const FIELD_INSURED_AMOUNT= 'insuredAmount';
    public const FIELD_BAR_CODE = 'barCode';
    public const FIELD_TRACK_AND_TRACE_URL = 'trackAndTraceUrl';

    /**
     * @var string|null
     */
    protected $consignmentReference;

    /**
     * @var ShippingOptionEntity
     */
    protected $shippingOption;

    /**
     * @var string
     */
    protected $shippingOptionId;

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
     * @var string|null
     */
    protected $labelUrl;

    /**
     * @var float
     */
    protected $insuredAmount = 0.0;

    /**
     * @var string|null
     */
    protected $barCode;

    /**
     * @var string|null
     */
    protected $trackAndTraceUrl;


    /**
     * @return int|null
     */
    public function getConsignmentReference(): ?string
    {
        return $this->consignmentReference;
    }

    /**
     * @param string|null $consignmentReference
     *
     * @return self
     */
    public function setConsignmentReference(?int $consignmentReference): self
    {
        $this->consignmentReference = $consignmentReference;
        return $this;
    }

    /**
     * @return ShippingOptionEntity
     */
    public function getShippingOption(): ShippingOptionEntity
    {
        return $this->shippingOption;
    }

    /**
     * @param ShippingOptionEntity $shippingOption
     *
     * @return self
     */
    public function setShippingOption(ShippingOptionEntity $shippingOption): self
    {
        $this->shippingOption = $shippingOption;
        return $this;
    }

    /**
     * @return string
     */
    public function getShippingOptionId(): string
    {
        return $this->shippingOptionId;
    }

    /**
     * @param string $shippingOptionId
     *
     * @return self
     */
    public function setShippingOptionId(string $shippingOptionId): self
    {
        $this->shippingOptionId = $shippingOptionId;
        return $this;
    }

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
     * @return string|null
     */
    public function getLabelUrl(): ?string
    {
        return $this->labelUrl;
    }

    /**
     * @param string|null $labelUrl
     *
     * @return self
     */
    public function setLabelUrl(?string $labelUrl): self
    {
        $this->labelUrl = $labelUrl;
        return $this;
    }

    /**
     * @return float
     */
    public function getInsuredAmount(): float
    {
        return $this->insuredAmount ?? 0.0;
    }

    /**
     * @param float $insuredAmount
     *
     * @return ShipmentEntity
     */
    public function setInsuredAmount(float $insuredAmount): ShipmentEntity
    {
        $this->insuredAmount = $insuredAmount;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBarCode(): ?string
    {
        return $this->barCode;
    }

    /**
     * @param string|null $barCode
     *
     * @return ShipmentEntity
     */
    public function setBarCode(?string $barCode): ShipmentEntity
    {
        $this->barCode = $barCode;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTrackAndTraceUrl(): ?string
    {
        return $this->trackAndTraceUrl;
    }

    /**
     * @param string|null $trackAndTraceUrl
     *
     * @return ShipmentEntity
     */
    public function setTrackAndTraceUrl(?string $trackAndTraceUrl): ShipmentEntity
    {
        $this->trackAndTraceUrl = $trackAndTraceUrl;
        return $this;
    }
}