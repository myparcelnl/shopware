<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Cart\Mapper;

use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcelNL\Pdk\App\Cart\Model\PdkCart;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Maps a calculated Shopware cart to a PdkCart for the delivery options.
 *
 * The delivery options need the product lines (weight, product settings), the
 * shipping costs, the chosen shipping method and the country of the shipping
 * address. Only product line items become lines, as in PdkOrderMapper. The cart
 * leaves the order totals at 0: the checkout context does not read them.
 *
 * shipmentPrice is the net shipping price. The PDK adds it to the delivery
 * option prices and then asks TaxService for the display price.
 *
 * The PDK computes hasDeliveryOptions, excludeParcelLockers and
 * minimumDropOffDelay of the shipping method from the lines, so the mapper does
 * not pass them.
 */
final class PdkCartMapper
{
    public function __construct(private readonly MoneyConverter $moneyConverter)
    {
    }

    /**
     * Maps one cart. Use it for the cart of the current storefront request.
     *
     * @param  array<string, PdkProduct> $products   the products of the cart lines, keyed by product id
     * @param  float                     $euroFactor EuroCurrency::getFactor()
     */
    public function map(Cart $cart, SalesChannelContext $context, array $products, float $euroFactor): PdkCart
    {
        $currencyFactor = $context->getCurrency()->getFactor();
        $taxStatus      = $cart->getPrice()->getTaxStatus();
        $shippingCosts  = $cart->getDeliveries()->first()?->getShippingCosts();
        $shipmentPrice  = null === $shippingCosts
            ? ['price' => 0, 'vat' => 0, 'priceAfterVat' => 0]
            : $this->moneyConverter->toUnitCents($shippingCosts, $currencyFactor, $euroFactor, $taxStatus);
        $shippingMethod = $context->getShippingMethod();

        return new PdkCart([
            'externalIdentifier'    => $cart->getToken(),
            'lines'                 => $this->mapLines($cart, $products, $currencyFactor, $euroFactor, $taxStatus),
            'shipmentPrice'         => $shipmentPrice['price'],
            'shipmentVat'           => $shipmentPrice['vat'],
            'shipmentPriceAfterVat' => $shipmentPrice['priceAfterVat'],
            'shippingMethod'        => [
                'id'              => $shippingMethod->getId(),
                'name'            => $shippingMethod->getTranslation('name') ?? $shippingMethod->getName(),
                'shippingAddress' => self::mapShippingAddress($context),
            ],
        ]);
    }

    /**
     * The product ids of the cart lines, for one batch read of the products.
     *
     * @return list<string>
     */
    public static function productIds(Cart $cart): array
    {
        $ids = [];

        foreach ($cart->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $ids[] = (string) $lineItem->getReferencedId();
        }

        return array_values(array_unique(array_filter($ids, static fn (string $id) => '' !== $id)));
    }

    /**
     * The address before the consumer logs in is only a country, so every field
     * except cc can be null. The company only decides isBusiness; the PDK
     * Address does not keep it.
     *
     * @return array<string, null|string>
     */
    private static function mapShippingAddress(SalesChannelContext $context): array
    {
        $location = $context->getShippingLocation();
        $address  = $location->getAddress();
        $iso      = $location->getCountry()->getIso();

        return [
            'cc'         => null === $iso ? null : strtoupper($iso),
            'postalCode' => $address?->getZipcode(),
            'city'       => $address?->getCity(),
            'street'     => $address?->getStreet(),
            'company'    => $address?->getCompany(),
        ];
    }

    /**
     * @param  array<string, PdkProduct> $products
     *
     * @return list<array<string, mixed>>
     */
    private function mapLines(Cart $cart, array $products, float $currencyFactor, float $euroFactor, string $taxStatus): array
    {
        $lines = [];

        foreach ($cart->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $price = $lineItem->getPrice();

            if (null === $price) {
                continue;
            }

            $productId = (string) $lineItem->getReferencedId();

            $lines[] = array_merge(
                $this->moneyConverter->toUnitCents($price, $currencyFactor, $euroFactor, $taxStatus),
                [
                    'quantity' => $lineItem->getQuantity(),
                    'product'  => $products[$productId] ?? [
                        'externalIdentifier' => $productId,
                        'name'               => $lineItem->getLabel(),
                        'weight'             => 0,
                    ],
                ]
            );
        }

        return $lines;
    }
}
