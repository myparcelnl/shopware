<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminView;

final class AdminViewMarkup
{
    /**
     * Notifications and modals can be empty. Without the boot element the app
     * cannot start, and without the view the admin shows an empty card: both
     * are errors, so the admin shows a message instead.
     *
     * @throws EmptyAdminViewException
     */
    public static function compose(
        AdminView $view,
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
