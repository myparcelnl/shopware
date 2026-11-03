<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

/**
 * Records the transitions it is asked to run, without a database. Every state
 * machine it gives has the transitions of the constructor. An action in
 * $failingActions throws, the way a refused transition does.
 */
final class RecordingStateMachineRegistry extends StateMachineRegistry
{
    /** @var list<Transition> */
    public array $transitions = [];

    /**
     * @param  list<StateMachineTransitionEntity> $stateMachineTransitions
     * @param  list<string>                       $failingActions
     */
    public function __construct(
        private readonly array $stateMachineTransitions = [],
        private readonly array $failingActions = []
    ) {
    }

    public function getStateMachine(string $name, Context $context): StateMachineEntity
    {
        $stateMachine = (new StateMachineEntity())->assign(['id' => Entities::id(), 'technicalName' => $name]);
        $stateMachine->setTransitions(new StateMachineTransitionCollection($this->stateMachineTransitions));

        return $stateMachine;
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
