<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Core\Content\ShippingMethod;

use Shopware\Core\Checkout\Shipping\ShippingMethodEntity as ShopwareShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ShippingMethodEntity extends Entity
{
    use EntityIdTrait;

    public const FIELD_ID = 'id';
    public const FIELD_CARRIER_ID = 'carrierId';
    public const FIELD_CARRIER_NAME = 'carrierName';
    public const FIELD_SHIPPING_METHOD = 'shippingMethod';
    public const FIELD_SHIPPING_METHOD_ID = 'shippingMethodId';

    /**
     * @var int
     */
    protected $carrierId;

    /**
     * @var string
     */
    protected $carrierName;

    /**
     * @var ShopwareShippingMethodEntity
     */
    protected $shippingMethod;

    /**
     * @var string
     */
    protected $shippingMethodId;

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
     * @return self
     */
    public function setCarrierId(int $carrierId): self
    {
        $this->carrierId = $carrierId;
        return $this;
    }

    /**
     * @return string
     */
    public function getCarrierName(): string
    {
        return $this->carrierName;
    }

    /**
     * @param string $carrierName
     *
     * @return self
     */
    public function setCarrierName(string $carrierName): self
    {
        $this->carrierName = $carrierName;
        return $this;
    }

    /**
     * @return ShopwareShippingMethodEntity
     */
    public function getShippingMethod(): ShopwareShippingMethodEntity
    {
        return $this->shippingMethod;
    }

    /**
     * @param ShopwareShippingMethodEntity $shippingMethod
     *
     * @return self
     */
    public function setShippingMethod(ShopwareShippingMethodEntity $shippingMethod): self
    {
        $this->shippingMethod = $shippingMethod;
        return $this;
    }

    /**
     * @return string
     */
    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    /**
     * @param string $shippingMethodId
     *
     * @return self
     */
    public function setShippingMethodId(string $shippingMethodId): self
    {
        $this->shippingMethodId = $shippingMethodId;
        return $this;
    }
}