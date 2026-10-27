<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminView;
use RuntimeException;

/**
 * The view needs an order that does not exist. The view route answers 404.
 */
final class AdminViewNotFoundException extends RuntimeException
{
    /**
     * Use when the ID in the request matches no order for the view.
     */
    public static function forView(AdminView $view, string $id): self
    {
        return new self(sprintf('The admin view "%s" found nothing for ID "%s".', $view->value, $id));
    }
}
