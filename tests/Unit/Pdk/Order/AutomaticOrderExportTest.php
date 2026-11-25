<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Order\AutomaticOrderExport;
use MyParcel\Shopware\Tests\Support\AutomaticExport;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\Account\Service\PdkAccountFeaturesService;
use MyParcelNL\Pdk\App\Api\Backend\PdkBackendActions;

it('exports the order when it enters the state set for automatic export', function () {
    $actions = AutomaticExport::boot();

    (new AutomaticOrderExport())->onOrderState(Entities::ORDER_ID, 'in_progress');

    expect($actions->calls)->toBe([[
        'action'     => PdkBackendActions::EXPORT_ORDERS,
        'parameters' => ['orderIds' => [Entities::ORDER_ID]],
        'automatic'  => true,
    ]]);
});

it('does not export the order when it enters another state', function () {
    $actions = AutomaticExport::boot();

    (new AutomaticOrderExport())->onOrderState(Entities::ORDER_ID, 'completed');

    expect($actions->calls)->toBe([]);
});

it('does not export when no state is set for automatic export', function () {
    $actions = AutomaticExport::boot(null);

    (new AutomaticOrderExport())->onOrderState(Entities::ORDER_ID, 'in_progress');

    expect($actions->calls)->toBe([]);
});

it('exports the order when the effective order mode is v2', function () {
    $actions = AutomaticExport::boot();
    TestPdk::useSubscriptionFeatures([PdkAccountFeaturesService::FEATURE_ORDER_MANAGEMENT]);

    (new AutomaticOrderExport())->onOrderState(Entities::ORDER_ID, 'in_progress');

    // The export runs in every order mode, as in WooCommerce. For V2 the PDK
    // takes the shipments path in ExportOrderAction::export() (INT-1594). The
    // skip comes back with INT-2218, once V2 without an active sales channel
    // drops to ORDER_MODE_SHIPMENTS.
    expect($actions->calls)->toBe([[
        'action'     => PdkBackendActions::EXPORT_ORDERS,
        'parameters' => ['orderIds' => [Entities::ORDER_ID]],
        'automatic'  => true,
    ]]);
});

it('does not export the order again when the export moves it into the same state', function () {
    $actions = AutomaticExport::boot();
    $export  = new AutomaticOrderExport();

    // The export can set the order state, which runs onOrderState() again in the same request.
    $actions->onExecute = static fn () => $export->onOrderState(Entities::ORDER_ID, 'in_progress');

    $export->onOrderState(Entities::ORDER_ID, 'in_progress');

    expect($actions->calls)->toHaveCount(1);
});

it('exports the order again after an export that failed', function () {
    $actions = AutomaticExport::boot();
    $export  = new AutomaticOrderExport();

    $actions->onExecute = static fn () => throw new RuntimeException('The API is down');

    expect(fn () => $export->onOrderState(Entities::ORDER_ID, 'in_progress'))->toThrow(RuntimeException::class);

    $actions->onExecute = null;
    $export->onOrderState(Entities::ORDER_ID, 'in_progress');

    expect($actions->calls)->toHaveCount(2);
});
