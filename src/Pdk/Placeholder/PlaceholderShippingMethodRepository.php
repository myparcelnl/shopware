<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Placeholder;

use MyParcelNL\Pdk\App\ShippingMethod\Collection\PdkShippingMethodCollection;
use MyParcelNL\Pdk\App\ShippingMethod\Contract\PdkShippingMethodRepositoryInterface;

/**
 * Temporary. It exists only so that the PDK container compiles in production
 * mode.
 *
 * @TODO INT-1961: replace with the real shipping method repository.
 */
final class PlaceholderShippingMethodRepository implements PdkShippingMethodRepositoryInterface
{
    public function all(): PdkShippingMethodCollection
    {
        throw NotImplementedException::forContract(PdkShippingMethodRepositoryInterface::class);
    }
}
