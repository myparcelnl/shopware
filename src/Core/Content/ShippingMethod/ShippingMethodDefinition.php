<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Core\Content\ShippingMethod;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition as ShopwareShippingMethodDefinition;

class ShippingMethodDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'kiener_my_parcel_shipping_method';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ShippingMethodCollection::class;
    }

    public function getEntityClass(): string
    {
        return ShippingMethodEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new IntField('carrier_id', 'carrierId'))->addFlags(new Required()),
            (new StringField('carrier_name', 'carrierName'))->addFlags(new Required()),
            (new FkField('shipping_method_id', 'shippingMethodId', ShopwareShippingMethodDefinition::class))->addFlags(new Required(), new CascadeDelete()),

            (new OneToOneAssociationField('shippingMethod', 'shipping_method_id', 'id', ShopwareShippingMethodDefinition::class))->addFlags(new Required(), new CascadeDelete()),
        ]);
    }
}