<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The consumer's choice of delivery options, kept as an extension of the cart.
 *
 * Shopware saves the cart with its extensions, and copies them to every
 * recalculated cart. The choice is an ArrayStruct, not a class of the plugin, so
 * a saved cart can still be read when the plugin is uninstalled.
 *
 * The choice remembers the shipping method and the country it was made for. A
 * choice for another method or country no longer applies: the widget showed
 * other options there.
 */
final class CartDeliveryOptions
{
    public const EXTENSION_NAME = 'myparcelDeliveryOptions';

    private const KEY_DELIVERY_OPTIONS  = 'deliveryOptions';
    private const KEY_SHIPPING_METHOD   = 'shippingMethodId';
    private const KEY_COUNTRY           = 'countryIso';

    /**
     * Stores the choice on the cart, for the shipping method and the country of
     * the context.
     *
     * @param  array<string, mixed> $deliveryOptions DeliveryOptions::toStorableArray()
     */
    public static function store(Cart $cart, array $deliveryOptions, SalesChannelContext $context): void
    {
        $cart->addExtension(self::EXTENSION_NAME, new ArrayStruct([
            self::KEY_DELIVERY_OPTIONS => $deliveryOptions,
            self::KEY_SHIPPING_METHOD  => $context->getShippingMethod()->getId(),
            self::KEY_COUNTRY          => self::countryIso($context),
        ]));
    }

    /**
     * Returns the stored choice, or null when the cart has none.
     *
     * @return null|array<string, mixed> DeliveryOptions::toStorableArray()
     */
    public static function read(Cart $cart): ?array
    {
        $extension = $cart->getExtension(self::EXTENSION_NAME);

        if (!$extension instanceof ArrayStruct || !is_array($extension->get(self::KEY_DELIVERY_OPTIONS))) {
            return null;
        }

        return $extension->get(self::KEY_DELIVERY_OPTIONS);
    }

    /**
     * Tells if the stored choice was made for the shipping method and the country
     * of the context.
     */
    public static function matches(Cart $cart, SalesChannelContext $context): bool
    {
        $extension = $cart->getExtension(self::EXTENSION_NAME);

        return $extension instanceof ArrayStruct
            && $extension->get(self::KEY_SHIPPING_METHOD) === $context->getShippingMethod()->getId()
            && $extension->get(self::KEY_COUNTRY) === self::countryIso($context);
    }

    /**
     * Removes the stored choice from the cart.
     */
    public static function forget(Cart $cart): void
    {
        $cart->removeExtension(self::EXTENSION_NAME);
    }

    private static function countryIso(SalesChannelContext $context): ?string
    {
        $iso = $context->getShippingLocation()->getCountry()->getIso();

        return null === $iso ? null : strtoupper($iso);
    }
}
