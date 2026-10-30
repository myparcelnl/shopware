<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminScreen;

/**
 * Puts the parts that the PDK renders for one admin screen together in the
 * right order.
 */
final class AdminViewMarkup
{
    /**
     * Join the bootstrap container, the notifications, the modals and the
     * component into the markup of one admin screen. Notifications and modals
     * can be empty. Without the bootstrap container the app cannot start, and
     * without the component the admin shows an empty card: both are errors, so
     * the admin shows a message instead.
     *
     * @throws EmptyAdminViewException
     */
    public static function compose(
        AdminScreen $view,
        string $initScript,
        string $notifications,
        string $modals,
        string $component
    ): string {
        if ('' === $initScript || '' === $component) {
            throw EmptyAdminViewException::forView($view);
        }

        return $initScript . $notifications . $modals . $component;
    }
}
