<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * Storefront checkout fixtures: a sales channel context and a calculated cart.
 */
final class Checkout
{
    public const SHIPPING_METHOD_ID = '0190a1b2c3d4e5f60718293a4b5c5001';
    public const TOKEN              = 'test-token';

    /**
     * A sales channel context. Keys: taxState (CartPrice::TAX_STATE_*),
     * currencyFactor, countryIso, zipcode, city, street, company, withAddress
     * (false gives a location with only the country).
     *
     * @param  array<string, mixed> $values
     */
    public static function context(array $values = []): SalesChannelContext
    {
        $country = new CountryEntity();
        $country->setId(Entities::id());
        $country->setIso($values['countryIso'] ?? 'NL');

        $location = new ShippingLocation($country);

        if ($values['withAddress'] ?? true) {
            $address = new CustomerAddressEntity();
            $address->setId(Entities::id());
            $address->setZipcode($values['zipcode'] ?? '2132WT');
            $address->setCity($values['city'] ?? 'Hoofddorp');
            $address->setStreet($values['street'] ?? 'Antareslaan 31');
            $address->setCountry($country);

            if (isset($values['company'])) {
                $address->setCompany($values['company']);
            }

            $location = new ShippingLocation($country, null, $address);
        }

        $currency = new CurrencyEntity();
        $currency->setId(Entities::id());
        $currency->setFactor($values['currencyFactor'] ?? 1.0);

        $context = Generator::generateSalesChannelContext(
            token: self::TOKEN,
            currency: $currency,
            shippingMethod: self::shippingMethod(),
            shippingLocation: $location
        );

        $context->setTaxState($values['taxState'] ?? CartPrice::TAX_STATE_GROSS);

        return $context;
    }

    /**
     * The shipping method "Standard" with id SHIPPING_METHOD_ID.
     */
    public static function shippingMethod(?string $id = null, string $name = 'Standard', bool $active = true): ShippingMethodEntity
    {
        $method = new ShippingMethodEntity();
        $method->setId($id ?? self::SHIPPING_METHOD_ID);
        $method->setName($name);
        $method->setTranslated(['name' => $name, 'description' => $name . ' delivery']);
        $method->setActive($active);

        return $method;
    }

    /**
     * A calculated cart. Keys: lines (list of [productId, quantity, unitPrice,
     * unitTax]), shippingCosts (gross), shippingTax, taxRules (TaxRuleCollection
     * of the shipping costs), taxStatus.
     *
     * @param  array<string, mixed> $values
     */
    public static function cart(array $values = []): Cart
    {
        $cart = new Cart(self::TOKEN);

        foreach ($values['lines'] ?? [[Entities::PRODUCT_ID, 2, 12.10, 2.10]] as [$productId, $quantity, $unitPrice, $unitTax]) {
            $lineItem = new LineItem(Entities::id(), LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, $quantity);
            $lineItem->setLabel('Testproduct');
            $lineItem->setPrice(new CalculatedPrice(
                $unitPrice,
                round($unitPrice * $quantity, 2),
                new CalculatedTaxCollection([new CalculatedTax($unitTax * $quantity, 21.0, $unitPrice * $quantity)]),
                new TaxRuleCollection(),
                $quantity
            ));

            $cart->add($lineItem);
        }

        $shippingCosts = $values['shippingCosts'] ?? 6.05;
        $shippingTax   = $values['shippingTax'] ?? 1.05;
        $taxRules      = $values['taxRules'] ?? new TaxRuleCollection([new TaxRule(21.0)]);

        $cart->setDeliveries(new DeliveryCollection([
            new Delivery(
                new DeliveryPositionCollection(),
                new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
                self::shippingMethod(),
                new ShippingLocation(new CountryEntity()),
                new CalculatedPrice(
                    $shippingCosts,
                    $shippingCosts,
                    new CalculatedTaxCollection([new CalculatedTax($shippingTax, 21.0, $shippingCosts)]),
                    $taxRules
                )
            ),
        ]));

        $cart->setPrice(new CartPrice(0, 30.25, 24.20, new CalculatedTaxCollection(), new TaxRuleCollection(), $values['taxStatus'] ?? CartPrice::TAX_STATE_GROSS));

        return $cart;
    }
}
