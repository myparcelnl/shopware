<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Entity\OrderData;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderDataEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderId;

    protected string $orderVersionId;

    protected ?string $apiIdentifier = null;

    /** @var null|array<string, mixed> */
    protected ?array $data = null;

    /** @var null|list<array<string, mixed>> */
    protected ?array $notes = null;

    protected ?OrderEntity $order = null;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function getApiIdentifier(): ?string
    {
        return $this->apiIdentifier;
    }

    /**
     * @return null|array<string, mixed>
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @return null|list<array<string, mixed>>
     */
    public function getNotes(): ?array
    {
        return $this->notes;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }
}
