<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\AutomaticOrderExport;
use MyParcel\Shopware\Pdk\Order\Subscriber\AutomaticOrderExportSubscriber;
use MyParcel\Shopware\Tests\Support\AutomaticExport;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\PdkServices;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use MyParcelNL\Pdk\App\Api\Backend\PdkBackendActions;
use Psr\Log\LogLevel;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;

/*
 * The subscriber runs the export of AutomaticOrderExportTest through the real
 * PdkInitializer, which TestPdk::boot() points at the mock PDK. INT-2151 covers
 * what these tests cannot: that Shopware itself dispatches the two events.
 */

/**
 * A transition of $entityName into $state, as Shopware dispatches it.
 */
function orderTransitionEvent(string $entityName, string $state): StateMachineTransitionEvent
{
    $from = new StateMachineStateEntity();
    $from->setTechnicalName('open');

    $to = new StateMachineStateEntity();
    $to->setTechnicalName($state);

    return new StateMachineTransitionEvent(
        $entityName,
        Entities::ORDER_ID,
        $from,
        $to,
        Context::createDefaultContext()
    );
}

/**
 * An order placed in the checkout, in $state. The checkout loads the order with
 * its state, so the subscriber reads the name without a second query.
 */
function orderPlacedIn(?string $state): CheckoutOrderPlacedEvent
{
    return new CheckoutOrderPlacedEvent(Checkout::context(), Entities::order(['state' => $state]));
}

function exportSubscriber(?RecordingLogger $logger = null): AutomaticOrderExportSubscriber
{
    return new AutomaticOrderExportSubscriber(
        PdkServices::initializer(),
        new AutomaticOrderExport(),
        $logger ?? new RecordingLogger()
    );
}

/**
 * The one call the export makes for the order of these tests.
 *
 * @return list<array{action: string, parameters: array<string, mixed>, automatic: bool}>
 */
function automaticExportCall(): array
{
    return [[
        'action'     => PdkBackendActions::EXPORT_ORDERS,
        'parameters' => ['orderIds' => [Entities::ORDER_ID]],
        'automatic'  => true,
    ]];
}

it('listens to the state machine transitions and to a placed order', function () {
    expect(AutomaticOrderExportSubscriber::getSubscribedEvents())
        ->toBe([
            StateMachineTransitionEvent::class => 'onTransition',
            CheckoutOrderPlacedEvent::class    => 'onOrderPlaced',
        ]);
});

it('exports the order that enters the state set for automatic export', function () {
    $actions = AutomaticExport::boot();

    exportSubscriber()->onTransition(orderTransitionEvent(OrderDefinition::ENTITY_NAME, 'in_progress'));

    expect($actions->calls)->toBe(automaticExportCall());
});

it('leaves the transitions of other entities alone', function () {
    $actions = AutomaticExport::boot();

    // Shopware dispatches the same event for order deliveries and transactions.
    exportSubscriber()->onTransition(orderTransitionEvent('order_delivery', 'in_progress'));

    expect($actions->calls)->toBe([]);
});

it('exports a new order that the checkout places in the state set for automatic export', function () {
    $actions = AutomaticExport::boot();

    // The checkout creates the order without a transition, so onTransition() never sees it.
    exportSubscriber()->onOrderPlaced(orderPlacedIn('in_progress'));

    expect($actions->calls)->toBe(automaticExportCall());
});

it('does not export a new order that the checkout places in another state', function () {
    $actions = AutomaticExport::boot();

    exportSubscriber()->onOrderPlaced(orderPlacedIn('open'));

    expect($actions->calls)->toBe([]);
});

it('does not export a new order that has no state', function () {
    $actions = AutomaticExport::boot();

    exportSubscriber()->onOrderPlaced(orderPlacedIn(null));

    expect($actions->calls)->toBe([]);
});

it('logs a failed export and lets the state change through', function () {
    $actions            = AutomaticExport::boot();
    $actions->onExecute = static fn () => throw new RuntimeException('The API is down');
    $logger             = new RecordingLogger();

    exportSubscriber($logger)->onTransition(orderTransitionEvent(OrderDefinition::ENTITY_NAME, 'in_progress'));

    expect($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe(LogLevel::ERROR)
        ->and($logger->records[0]['message'])->toBe('MyParcel: the automatic export of an order failed')
        ->and($logger->records[0]['context']['orderId'])->toBe(Entities::ORDER_ID)
        ->and($logger->records[0]['context']['exception'])->toBeInstanceOf(RuntimeException::class);
});
