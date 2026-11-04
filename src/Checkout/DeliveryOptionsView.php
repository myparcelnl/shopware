<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

use Shopware\Core\Framework\Struct\Struct;

/**
 * What the confirm page needs to show the delivery options: the wrapper with the
 * checkout context, and the script and stylesheet of the widget. The Twig
 * template reads it as page.extensions.myparcel.
 */
final class DeliveryOptionsView extends Struct
{
    public const EXTENSION_NAME = 'myparcel';

    public function __construct(
        public readonly string $html,
        public readonly string $scriptUrl,
        public readonly string $styleUrl
    ) {
    }

    public function getApiAlias(): string
    {
        return 'myparcel_delivery_options_view';
    }
}
