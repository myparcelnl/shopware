<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\State;

/**
 * Finds the shortest list of state machine actions from one state to another.
 *
 * Shopware only changes an order state through transitions, and the state that a
 * merchant picks in the MyParcel settings is often not one step away (open to
 * completed goes through in_progress). Breadth-first search: the first path that
 * reaches the target has the fewest steps.
 */
final class ShortestTransitionPath
{
    /**
     * Returns the actions that move an order from one state to another along the
     * fewest transitions. Use it before a state change, to know which actions to run
     * in order.
     *
     * @param  list<array{action: string, from: string, to: string}> $transitions
     *
     * @return null|list<string> the action names in order, [] when $from is $to, null when $to cannot be reached
     */
    public static function find(array $transitions, string $from, string $to): ?array
    {
        if ($from === $to) {
            return [];
        }

        $visited = [$from => true];
        $queue   = [[$from, []]];

        while ([] !== $queue) {
            [$state, $path] = array_shift($queue);

            foreach ($transitions as $transition) {
                if ($transition['from'] !== $state || isset($visited[$transition['to']])) {
                    continue;
                }

                $next = [...$path, $transition['action']];

                if ($transition['to'] === $to) {
                    return $next;
                }

                $visited[$transition['to']] = true;
                $queue[]                    = [$transition['to'], $next];
            }
        }

        return null;
    }
}
