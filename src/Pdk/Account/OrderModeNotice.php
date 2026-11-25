<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Account;

use MyParcelNL\Pdk\Account\Contract\AccountFeaturesServiceInterface;
use MyParcelNL\Pdk\Facade\AccountSettings;
use MyParcelNL\Pdk\Facade\Language;
use MyParcelNL\Pdk\Facade\Notifications;
use MyParcelNL\Pdk\Notification\Model\Notification;

/**
 * Tells a merchant with order management what the plugin does for them, and
 * what it does not.
 *
 * Their account has order management, but Shopware orders do not reach MyParcel
 * by themselves: there is no Shopware sales channel and the shop cannot connect
 * yet. Creating shipments works as it always has. Without this notice the merchant
 * sees nothing of that and waits for orders that never arrive.
 *
 * @TODO INT-2218: drop the notice for a shop that is connected. Its orders do
 *       reach MyParcel, so there is nothing to warn about.
 */
final class OrderModeNotice
{
    public const TITLE_KEY   = 'shopware_admin_order_mode_v2_notice_title';
    public const MESSAGE_KEY = 'shopware_admin_order_mode_v2_notice_message';

    /**
     * Adds the notice when the effective order mode is V2. Call it in a request
     * that gives its notifications back to the admin. The PDK must be booted.
     */
    public function add(): void
    {
        if (AccountFeaturesServiceInterface::ORDER_MODE_V2 !== AccountSettings::getEffectiveOrderMode()) {
            return;
        }

        Notifications::warning(
            Language::translate(self::TITLE_KEY),
            Language::translate(self::MESSAGE_KEY),
            Notification::CATEGORY_GENERAL
        );
    }
}
