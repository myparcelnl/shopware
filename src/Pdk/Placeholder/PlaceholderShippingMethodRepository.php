<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Placeholder;

use MyParcelNL\Pdk\App\ShippingMethod\Collection\PdkShippingMethodCollection;
use MyParcelNL\Pdk\App\ShippingMethod\Contract\PdkShippingMethodRepositoryInterface;

/**
 * Temporary. Lists no shipping methods, so that the checkout section of the
 * plugin settings renders. INT-1961 lists the Shopware shipping methods.
 */
final class PlaceholderShippingMethodRepository implements PdkShippingMethodRepositoryInterface
{
    public function all(): PdkShippingMethodCollection
    {
        return new PdkShippingMethodCollection();
    }
}
