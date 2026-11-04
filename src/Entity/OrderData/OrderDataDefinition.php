<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Entity\OrderData;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * The PDK's order data and notes of one order.
 */
class OrderDataDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'myparcel_order_data';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderDataCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderDataEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware(AdminApiSource::class)),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required(), new ApiAware(AdminApiSource::class)),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required(), new ApiAware(AdminApiSource::class)),
            (new StringField('api_identifier', 'apiIdentifier'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new JsonField('data', 'data'))->addFlags(new ApiAware(AdminApiSource::class)),
            (new JsonField('notes', 'notes'))->addFlags(new ApiAware(AdminApiSource::class)),
            new OneToOneAssociationField('order', 'order_id', 'id', OrderDefinition::class, false),
        ]);
    }
}
