<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Placeholder;

use MyParcelNL\Pdk\App\Order\Collection\PdkOrderCollection;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\Base\Contract\ModelInterface;
use MyParcelNL\Pdk\Base\Support\Collection;

/**
 * Temporary. It exists only so that the PDK container compiles in production
 * mode. The adapter-layer tickets of epic INT-1748 replace it with the real
 * implementation.
 */
final class PlaceholderOrderRepository implements PdkOrderRepositoryInterface
{
    public function get($input): PdkOrder
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }

    public function find($id): ?PdkOrder
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }

    public function getMany($orderIds): PdkOrderCollection
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }

    public function update(PdkOrder $order): PdkOrder
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }

    public function updateMany(PdkOrderCollection $collection): PdkOrderCollection
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }

    /**
     * @param  int[]|string[] $ids
     */
    public function findAll(array $ids): Collection
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }

    public function findOrFail($id): ModelInterface
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }

    public function all(): Collection
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }

    public function exists($id): bool
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }

    public function retrieve(string $key, ?callable $callback = null, bool $force = false)
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }

    public function save(string $key, $data)
    {
        throw NotImplementedException::forContract(PdkOrderRepositoryInterface::class);
    }
}
