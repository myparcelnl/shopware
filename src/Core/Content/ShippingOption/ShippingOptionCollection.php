<?php


namespace Kiener\KienerMyParcel\Core\Content\ShippingOption;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                      add(ShippingOptionEntity $entity)
 * @method void                      set(string $key, ShippingOptionEntity $entity)
 * @method ShippingOptionEntity[]    getIterator()
 * @method ShippingOptionEntity[]    getElements()
 * @method ShippingOptionEntity|null get(string $key)
 * @method ShippingOptionEntity|null first()
 * @method ShippingOptionEntity|null last()
 */
class ShippingOptionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ShippingOptionEntity::class;
    }
}