<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Order\Contract\OrderStateTransitionerInterface;

/**
 * Records the transitions it was asked for.
 */
final class RecordingOrderStateTransitioner implements OrderStateTransitionerInterface
{
    /** @var list<array{orderIds: list<string>, technicalName: string}> */
    public array $calls = [];

    public function transitionTo(array $orderIds, string $technicalName): void
    {
        $this->calls[] = ['orderIds' => $orderIds, 'technicalName' => $technicalName];
    }
}
