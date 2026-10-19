<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Entity\OrderShipment;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderShipmentEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderId;

    protected string $orderVersionId;

    protected ?int $shipmentId = null;

    protected int $position = 0;

    /** @var array<string, mixed> */
    protected array $data = [];

    protected ?OrderEntity $order = null;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function getShipmentId(): ?int
    {
        return $this->shipmentId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }
}
