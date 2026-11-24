<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\AutomaticOrderExport;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\RecordingActionsService;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\Account\Service\PdkAccountFeaturesService;
use MyParcelNL\Pdk\App\Account\Contract\PdkAccountRepositoryInterface;
use MyParcelNL\Pdk\App\Api\Backend\PdkBackendActions;
use MyParcelNL\Pdk\App\Api\Contract\PdkActionsServiceInterface;
use MyParcelNL\Pdk\Base\Support\Collection;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Settings\Model\OrderSettings;
use function MyParcelNL\Pdk\Tests\factory as pdkFactory;

/**
 * Boots the PDK with automatic export on $state and returns the actions it runs.
 */
function bootAutomaticExport(?string $state = 'in_progress'): RecordingActionsService
{
    $actions = new RecordingActionsService();

    TestPdk::boot([PdkActionsServiceInterface::class => $actions]);

    if (null !== $state) {
        pdkFactory(OrderSettings::class)->withProcessDirectly($state)->store();
    }

    return $actions;
}

/**
 * Gives the account the order management feature, so the effective order mode is V2.
 */
function useOrderModeV2(): void
{
    /** @var PdkAccountRepositoryInterface $accounts */
    $accounts = Pdk::get(PdkAccountRepositoryInterface::class);
    $account  = $accounts->getAccount();

    $account->subscriptionFeatures = new Collection([PdkAccountFeaturesService::FEATURE_ORDER_MANAGEMENT]);
    $accounts->store($account);
}

it('exports the order when it enters the state set for automatic export', function () {
    $actions = bootAutomaticExport();

    (new AutomaticOrderExport())->onOrderState(Entities::ORDER_ID, 'in_progress');

    expect($actions->calls)->toBe([[
        'action'     => PdkBackendActions::EXPORT_ORDERS,
        'parameters' => ['orderIds' => [Entities::ORDER_ID]],
        'automatic'  => true,
    ]]);
});

it('does not export the order when it enters another state', function () {
    $actions = bootAutomaticExport();

    (new AutomaticOrderExport())->onOrderState(Entities::ORDER_ID, 'completed');

    expect($actions->calls)->toBe([]);
});

it('does not export when no state is set for automatic export', function () {
    $actions = bootAutomaticExport(null);

    (new AutomaticOrderExport())->onOrderState(Entities::ORDER_ID, 'in_progress');

    expect($actions->calls)->toBe([]);
});

it('exports the order when the effective order mode is v2', function () {
    $actions = bootAutomaticExport();
    useOrderModeV2();

    (new AutomaticOrderExport())->onOrderState(Entities::ORDER_ID, 'in_progress');

    // Order mode V2 without an active sales channel behaves as shipments mode
    // (INT-1594). Shopware has no sales channel, so this export cannot create a
    // second shipment next to one of an order landscape.
    expect($actions->calls)->toBe([[
        'action'     => PdkBackendActions::EXPORT_ORDERS,
        'parameters' => ['orderIds' => [Entities::ORDER_ID]],
        'automatic'  => true,
    ]]);
});

it('does not export the order again when the export moves it into the same state', function () {
    $actions = bootAutomaticExport();
    $export  = new AutomaticOrderExport();

    // The export can set the order state, which runs onOrderState() again in the same request.
    $actions->onExecute = static fn () => $export->onOrderState(Entities::ORDER_ID, 'in_progress');

    $export->onOrderState(Entities::ORDER_ID, 'in_progress');

    expect($actions->calls)->toHaveCount(1);
});

it('exports the order again after an export that failed', function () {
    $actions = bootAutomaticExport();
    $export  = new AutomaticOrderExport();

    $actions->onExecute = static fn () => throw new RuntimeException('The API is down');

    expect(fn () => $export->onOrderState(Entities::ORDER_ID, 'in_progress'))->toThrow(RuntimeException::class);

    $actions->onExecute = null;
    $export->onOrderState(Entities::ORDER_ID, 'in_progress');

    expect($actions->calls)->toHaveCount(2);
});
