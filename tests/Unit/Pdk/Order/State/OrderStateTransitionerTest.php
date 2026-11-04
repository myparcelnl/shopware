<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\State\OrderStateTransitioner;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use MyParcel\Shopware\Tests\Support\RecordingStateMachineRegistry;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\StateMachine\Transition;

/**
 * A registry with the transitions of order.state of a default Shopware 6.7
 * install, in part.
 *
 * @param  list<string> $failingActions
 */
function orderStateRegistry(array $failingActions = []): RecordingStateMachineRegistry
{
    return new RecordingStateMachineRegistry([
        Entities::transition('process', 'open', 'in_progress'),
        Entities::transition('cancel', 'open', 'cancelled'),
        Entities::transition('complete', 'in_progress', 'completed'),
        Entities::transition('cancel', 'in_progress', 'cancelled'),
    ], $failingActions);
}

it('runs the direct transition to the target state', function () {
    $registry = orderStateRegistry();
    $order    = Entities::order(['state' => 'open']);

    (new OrderStateTransitioner(FakeEntityRepository::with($order), $registry, new RecordingLogger()))
        ->transitionTo([Entities::ORDER_ID], 'in_progress');

    expect($registry->transitions)->toEqual([new Transition(OrderDefinition::ENTITY_NAME, Entities::ORDER_ID, 'process', 'stateId')]);
});

it('skips and logs an order without a direct transition, without intermediate states', function () {
    $registry = orderStateRegistry();
    $logger   = new RecordingLogger();
    $order    = Entities::order(['state' => 'open']);

    (new OrderStateTransitioner(FakeEntityRepository::with($order), $registry, $logger))
        ->transitionTo([Entities::ORDER_ID], 'completed');

    expect($registry->transitions)->toBe([])
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe('warning')
        ->and($logger->records[0]['context'])->toBe(['orderId' => Entities::ORDER_ID, 'from' => 'open', 'to' => 'completed']);
});

it('leaves an order alone that is in the target state already', function () {
    $registry = orderStateRegistry();
    $logger   = new RecordingLogger();
    $order    = Entities::order(['state' => 'completed']);

    (new OrderStateTransitioner(FakeEntityRepository::with($order), $registry, $logger))
        ->transitionTo([Entities::ORDER_ID], 'completed');

    expect($registry->transitions)->toBe([])
        ->and($logger->records)->toBe([]);
});

it('logs a failed transition and goes on with the next order', function () {
    $registry = orderStateRegistry(['cancel']);
    $logger   = new RecordingLogger();
    $other    = Entities::order(['id' => Entities::id(), 'state' => 'in_progress']);
    $orders   = FakeEntityRepository::with(Entities::order(['state' => 'open']), $other);

    (new OrderStateTransitioner($orders, $registry, $logger))
        ->transitionTo([Entities::ORDER_ID, $other->getId()], 'cancelled');

    expect($registry->transitions)->toBe([])
        ->and(array_column($logger->records, 'level'))->toBe(['error', 'error']);
});

it('ignores ids that are not order ids', function () {
    $orders = FakeEntityRepository::with();

    (new OrderStateTransitioner($orders, orderStateRegistry(), new RecordingLogger()))
        ->transitionTo(['not-an-id'], 'completed');

    expect($orders->criteria)->toBe([]);
});
