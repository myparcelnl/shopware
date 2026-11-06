<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

use MyParcel\Shopware\Pdk\View\AdminScreen;
use RuntimeException;

/**
 * The screen needs an order or a product that does not exist. The view route answers 404.
 */
final class AdminViewNotFoundException extends RuntimeException
{
    /**
     * Create the exception for an ID in the request that matches no order or product.
     */
    public static function forScreen(AdminScreen $screen, string $id): self
    {
        return new self(sprintf('The admin screen "%s" found nothing for ID "%s".', $screen->value, $id));
    }
}
