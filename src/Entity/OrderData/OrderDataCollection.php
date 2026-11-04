<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Entity\OrderData;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OrderDataEntity>
 */
class OrderDataCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderDataEntity::class;
    }
}
