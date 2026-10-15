<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Placeholder;

use MyParcelNL\Pdk\App\Cart\Collection\PdkCartCollection;
use MyParcelNL\Pdk\App\Cart\Contract\PdkCartRepositoryInterface;
use MyParcelNL\Pdk\App\Cart\Model\PdkCart;

/**
 * Temporary. It exists only so that the PDK container compiles in production
 * mode.
 *
 * @TODO INT-1961: replace with the real cart repository.
 */
final class PlaceholderCartRepository implements PdkCartRepositoryInterface
{
    public function get($input): PdkCart
    {
        throw NotImplementedException::forContract(PdkCartRepositoryInterface::class);
    }

    public function getMany($input): PdkCartCollection
    {
        throw NotImplementedException::forContract(PdkCartRepositoryInterface::class);
    }

    public function retrieve(string $key, ?callable $callback = null, bool $force = false)
    {
        throw NotImplementedException::forContract(PdkCartRepositoryInterface::class);
    }

    public function save(string $key, $data)
    {
        throw NotImplementedException::forContract(PdkCartRepositoryInterface::class);
    }
}
