<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\State\ShortestTransitionPath;

/**
 * The transitions of Shopware's default order.state machine.
 */
function orderTransitions(): array
{
    return [
        ['action' => 'process', 'from' => 'open', 'to' => 'in_progress'],
        ['action' => 'cancel', 'from' => 'open', 'to' => 'cancelled'],
        ['action' => 'complete', 'from' => 'in_progress', 'to' => 'completed'],
        ['action' => 'cancel', 'from' => 'in_progress', 'to' => 'cancelled'],
        ['action' => 'reopen', 'from' => 'cancelled', 'to' => 'open'],
        ['action' => 'reopen', 'from' => 'completed', 'to' => 'open'],
    ];
}

it('finds a direct transition', function () {
    expect(ShortestTransitionPath::find(orderTransitions(), 'open', 'in_progress'))->toBe(['process']);
});

it('finds the shortest path over several steps', function () {
    expect(ShortestTransitionPath::find(orderTransitions(), 'open', 'completed'))->toBe(['process', 'complete'])
        ->and(ShortestTransitionPath::find(orderTransitions(), 'cancelled', 'completed'))->toBe(['reopen', 'process', 'complete']);
});

it('needs no step when the order is already in the target state', function () {
    expect(ShortestTransitionPath::find(orderTransitions(), 'completed', 'completed'))->toBe([]);
});

it('returns null when the target cannot be reached', function () {
    expect(ShortestTransitionPath::find(orderTransitions(), 'open', 'does_not_exist'))->toBeNull()
        ->and(ShortestTransitionPath::find([], 'open', 'completed'))->toBeNull();
});

it('does not loop on a cycle', function () {
    $cycle = [
        ['action' => 'a', 'from' => 'x', 'to' => 'y'],
        ['action' => 'b', 'from' => 'y', 'to' => 'x'],
    ];

    expect(ShortestTransitionPath::find($cycle, 'x', 'z'))->toBeNull();
});
