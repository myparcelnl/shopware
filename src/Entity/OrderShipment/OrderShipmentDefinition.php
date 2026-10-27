<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Entity\OrderShipment;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * One MyParcel shipment of an order, as the PDK stores it.
 */
class OrderShipmentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'myparcel_order_shipment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderShipmentCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderShipmentEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware(AdminApiSource::class)),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required(), new ApiAware(AdminApiSource::class)),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required(), new ApiAware(AdminApiSource::class)),
            (new IntField('shipment_id', 'shipmentId'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new IntField('position', 'position'))->addFlags(new Required(), new ApiAware(AdminApiSource::class)),
            (new JsonField('data', 'data'))->addFlags(new Required(), new ApiAware(AdminApiSource::class)),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
        ]);
    }
}
