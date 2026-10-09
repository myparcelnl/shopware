<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminView;
use RuntimeException;

/**
 * The PDK rendered nothing: it logs the cause and returns an empty string when
 * it cannot build a context.
 */
final class EmptyAdminViewException extends RuntimeException
{
    public static function forView(AdminView $view): self
    {
        return new self(sprintf('The PDK rendered no markup for the admin view "%s". The MyParcel log has the cause.', $view->value));
    }
}
