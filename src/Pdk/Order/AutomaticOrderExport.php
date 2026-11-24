<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order;

use MyParcelNL\Pdk\App\Api\Backend\PdkBackendActions;
use MyParcelNL\Pdk\Facade\Actions;
use MyParcelNL\Pdk\Facade\Settings;
use MyParcelNL\Pdk\Settings\Model\OrderSettings;

/**
 * Exports an order when it enters the order state that the order settings set
 * for automatic export (processDirectly). The PDK must be booted.
 *
 * The PDK does not export an order that it exported automatically before. The
 * export can change the order state itself, which calls this class again in
 * the same request; that call does nothing while the first export runs.
 *
 * The export runs in every order mode, the same as the WooCommerce plugin. The
 * PDK picks the path per mode in ExportOrderAction::export().
 */
final class AutomaticOrderExport
{
    /** @var array<string, true> */
    private array $inProgress = [];

    /**
     * Exports the order when $state is the state set for automatic export. Use
     * it after an order changed state.
     *
     * @TODO INT-2218: skip the export when the effective order mode is V2. A
     *       skip is only correct after that ticket lowers V2 without an active
     *       sales channel to ORDER_MODE_SHIPMENTS. WooCommerce gets the same skip.
     *
     * @param  string $state the technical name of the new order state
     */
    public function onOrderState(string $orderId, string $state): void
    {
        if ($state !== (string) Settings::get(OrderSettings::PROCESS_DIRECTLY, OrderSettings::ID)
            || isset($this->inProgress[$orderId])
        ) {
            return;
        }

        $this->inProgress[$orderId] = true;

        try {
            Actions::executeAutomatic(PdkBackendActions::EXPORT_ORDERS, ['orderIds' => [$orderId]]);
        } finally {
            // An export that failed must stay possible later in this request.
            unset($this->inProgress[$orderId]);
        }
    }
}
