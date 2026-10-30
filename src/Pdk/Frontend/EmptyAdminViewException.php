<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminScreen;
use RuntimeException;

/**
 * The PDK rendered nothing: it logs the cause and returns an empty string when
 * it cannot build a context.
 */
final class EmptyAdminViewException extends RuntimeException
{
    /**
     * Create the exception for a screen whose component or bootstrap container
     * the PDK rendered empty.
     */
    public static function forView(AdminScreen $view): self
    {
        return new self(sprintf('The PDK rendered no markup for the admin view "%s". The MyParcel log has the cause.', $view->value));
    }
}
