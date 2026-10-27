<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Entity\OrderShipment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OrderShipmentEntity>
 */
class OrderShipmentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderShipmentEntity::class;
    }
}
