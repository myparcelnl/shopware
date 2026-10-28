<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Order\Contract\OrderStorageInterface;

/**
 * Keeps snapshots and stored PDK data in arrays. The repositories write through
 * OrderDataWriter, so a test reads the writes from its fake repositories.
 */
final class InMemoryOrderStorage implements OrderStorageInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $snapshots = [];

    /** @var array<string, list<array<string, mixed>>> */
    public array $shipments = [];

    /** @var array<string, list<array<string, mixed>>> */
    public array $notes = [];

    /** @var array<string, string> */
    public array $customerComments = [];

    /** @var array<string, string> apiIdentifier => orderId */
    public array $apiIdentifiers = [];

    public int $snapshotReads = 0;

    public function getSnapshot(string $orderId): ?array
    {
        return $this->getSnapshots([$orderId])[$orderId] ?? null;
    }

    public function getSnapshots(array $orderIds): array
    {
        $this->snapshotReads++;

        return array_intersect_key($this->snapshots, array_flip($orderIds));
    }

    public function findIdByApiIdentifier(string $apiIdentifier): ?string
    {
        return $this->apiIdentifiers[$apiIdentifier] ?? null;
    }

    public function getShipments(string $orderId): array
    {
        return $this->shipments[$orderId] ?? [];
    }

    public function getNotes(string $orderId): array
    {
        return ['notes' => $this->notes[$orderId] ?? [], 'customerComment' => $this->customerComments[$orderId] ?? null];
    }
}
