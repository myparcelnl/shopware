<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\ShippingMethod\Repository;

use MyParcelNL\Pdk\App\ShippingMethod\Collection\PdkShippingMethodCollection;
use MyParcelNL\Pdk\App\ShippingMethod\Contract\PdkShippingMethodRepositoryInterface;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * Lists the Shopware shipping methods for the checkout setting
 * allowedShippingMethods, where the merchant links shipping methods to package
 * types. The plugin creates no shipping methods of its own.
 *
 * The id is the shipping method id, the same value as the shipping method of
 * the sales channel context, so the PdkCart and the setting match.
 */
final class PdkShippingMethodRepository implements PdkShippingMethodRepositoryInterface
{
    /**
     * @param  EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     */
    public function __construct(private readonly EntityRepository $shippingMethodRepository)
    {
    }

    /**
     * Returns every shipping method, also the inactive ones: the settings page
     * shows only the enabled ones, and a method that the merchant activates
     * later keeps its package types.
     */
    public function all(): PdkShippingMethodCollection
    {
        $criteria = (new Criteria())->addSorting(new FieldSorting('position'), new FieldSorting('name'));
        $methods  = [];

        /** @var ShippingMethodEntity $method */
        foreach ($this->shippingMethodRepository->search($criteria, Context::createDefaultContext())->getEntities() as $method) {
            $methods[] = [
                'id'          => $method->getId(),
                'name'        => $method->getTranslation('name') ?? $method->getName() ?? $method->getId(),
                'description' => $method->getTranslation('description') ?? $method->getDescription(),
                'isEnabled'   => $method->getActive(),
            ];
        }

        return new PdkShippingMethodCollection($methods);
    }
}
