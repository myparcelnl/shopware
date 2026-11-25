<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\AutomaticOrderExport;
use MyParcel\Shopware\Subscriber\AutomaticOrderExportSubscriber;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\PdkServices;
use MyParcel\Shopware\Tests\Support\RecordingActionsService;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Api\Backend\PdkBackendActions;
use MyParcelNL\Pdk\App\Api\Contract\PdkActionsServiceInterface;
use MyParcelNL\Pdk\Settings\Model\OrderSettings;
use Psr\Log\LogLevel;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use function MyParcelNL\Pdk\Tests\factory as pdkFactory;

/*
 * The subscriber runs the export of AutomaticOrderExportTest through the real
 * PdkInitializer, which TestPdk::boot() points at the mock PDK. INT-2151 covers
 * the order path in a Shopware installation.
 */

/**
 * Boots the PDK with automatic export on "in_progress" and returns the actions it runs.
 */
function bootSubscriberExport(): RecordingActionsService
{
    $actions = new RecordingActionsService();

    TestPdk::boot([PdkActionsServiceInterface::class => $actions]);
    pdkFactory(OrderSettings::class)->withProcessDirectly('in_progress')->store();

    return $actions;
}

/**
 * A transition of $entityName into $state, as Shopware dispatches it.
 */
function transitionTo(string $entityName, string $state): StateMachineTransitionEvent
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

function subscriber(?RecordingLogger $logger = null): AutomaticOrderExportSubscriber
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
function exportCall(): array
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
    $actions = bootSubscriberExport();

    subscriber()->onTransition(transitionTo(OrderDefinition::ENTITY_NAME, 'in_progress'));

    expect($actions->calls)->toBe(exportCall());
});

it('leaves the transitions of other entities alone', function () {
    $actions = bootSubscriberExport();

    // Shopware dispatches the same event for order deliveries and transactions.
    subscriber()->onTransition(transitionTo('order_delivery', 'in_progress'));

    expect($actions->calls)->toBe([]);
});

it('exports a new order that the checkout places in the state set for automatic export', function () {
    $actions = bootSubscriberExport();

    // The checkout creates the order without a transition, so onTransition() never sees it.
    subscriber()->onOrderPlaced(orderPlacedIn('in_progress'));

    expect($actions->calls)->toBe(exportCall());
});

it('does not export a new order that the checkout places in another state', function () {
    $actions = bootSubscriberExport();

    subscriber()->onOrderPlaced(orderPlacedIn('open'));

    expect($actions->calls)->toBe([]);
});

it('does not export a new order that has no state', function () {
    $actions = bootSubscriberExport();

    subscriber()->onOrderPlaced(orderPlacedIn(null));

    expect($actions->calls)->toBe([]);
});

it('logs a failed export and lets the state change through', function () {
    $actions            = bootSubscriberExport();
    $actions->onExecute = static fn () => throw new RuntimeException('The API is down');
    $logger             = new RecordingLogger();

    subscriber($logger)->onTransition(transitionTo(OrderDefinition::ENTITY_NAME, 'in_progress'));

    expect($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe(LogLevel::ERROR)
        ->and($logger->records[0]['message'])->toBe('MyParcel: the automatic export of an order failed')
        ->and($logger->records[0]['context']['orderId'])->toBe(Entities::ORDER_ID)
        ->and($logger->records[0]['context']['exception'])->toBeInstanceOf(RuntimeException::class);
});
