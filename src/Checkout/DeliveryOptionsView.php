<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

use Shopware\Core\Framework\Struct\Struct;

/**
 * What the confirm page needs to show the delivery options: the wrapper with the
 * checkout context, the script and stylesheet of the widget, and the second
 * address line for the widget. The Twig template reads it as
 * page.extensions.myparcel.
 *
 * address2 is additionalAddressLine1 when that line holds the house number, by
 * the rule of AddressMapper, and otherwise empty. The js-pdk joins it to the
 * street, so the widget gets the same house number as the order.
 */
final class DeliveryOptionsView extends Struct
{
    public const EXTENSION_NAME = 'myparcel';

    public function __construct(
        public readonly string $html,
        public readonly string $scriptUrl,
        public readonly string $styleUrl,
        public readonly string $address2
    ) {
    }

    public function getApiAlias(): string
    {
        return 'myparcel_delivery_options_view';
    }
}
