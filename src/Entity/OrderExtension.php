<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Entity;

use MyParcel\Shopware\Entity\OrderData\OrderDataDefinition;
use MyParcel\Shopware\Entity\OrderShipment\OrderShipmentDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Lets one order criteria load the PDK data and shipments with the order. Not
 * autoloaded: only the plugin's storage asks for them.
 */
class OrderExtension extends EntityExtension
{
    public const ORDER_DATA = 'myparcelOrderData';
    public const SHIPMENTS  = 'myparcelShipments';

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new OneToOneAssociationField(self::ORDER_DATA, 'id', 'order_id', OrderDataDefinition::class, false));
        $collection->add(new OneToManyAssociationField(self::SHIPMENTS, OrderShipmentDefinition::class, 'order_id'));
    }

    public function getEntityName(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }
}
