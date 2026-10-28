<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

/**
 * Records the transitions it is asked to run, without a database. An action in
 * $failingActions throws, the way a refused transition does.
 */
final class RecordingStateMachineRegistry extends StateMachineRegistry
{
    /** @var list<Transition> */
    public array $transitions = [];

    /**
     * @param  list<string> $failingActions
     */
    public function __construct(private readonly array $failingActions = [])
    {
    }

    public function transition(Transition $transition, Context $context): StateMachineStateCollection
    {
        if (in_array($transition->getTransitionName(), $this->failingActions, true)) {
            throw new RuntimeException('The transition was refused');
        }

        $this->transitions[] = $transition;

        return new StateMachineStateCollection();
    }
}
