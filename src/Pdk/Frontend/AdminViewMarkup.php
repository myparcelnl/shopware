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
        AdminScreen $screen,
        string $initScript,
        string $notifications,
        string $modals,
        string $component
    ): string {
        if ('' === $component) {
            throw EmptyAdminViewException::forScreen($screen);
        }

        return self::composeShell($screen, $initScript, $notifications, $modals) . $component;
    }

    /**
     * Join the bootstrap container, the notifications and the modals into the
     * shell of a screen that renders its components one by one, such as the
     * order list. Without the bootstrap container the app cannot start, so the
     * admin shows a message instead.
     *
     * @throws EmptyAdminViewException
     */
    public static function composeShell(
        AdminScreen $screen,
        string $initScript,
        string $notifications,
        string $modals
    ): string {
        if ('' === $initScript) {
            throw EmptyAdminViewException::forScreen($screen);
        }

        return $initScript . $notifications . $modals;
    }
}
