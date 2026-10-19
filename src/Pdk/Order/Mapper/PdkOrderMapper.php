<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Mapper;

use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcelNL\Pdk\App\Order\Model\PdkOrder;
use MyParcelNL\Pdk\Facade\Logger;

/**
 * Maps a Shopware order snapshot to a PdkOrder.
 *
 * Only product line items become order lines, like in the WooCommerce plugin:
 * weight and customs need products, not promotions. The PDK computes the order
 * totals from the lines and the shipment price, so the mapper does not pass them.
 */
final class PdkOrderMapper
{
    private const LINE_TYPE_PRODUCT = 'product';

    public function __construct(
        private readonly AddressMapper $addressMapper,
        private readonly MoneyConverter $moneyConverter,
        private readonly PdkProductMapper $productMapper
    ) {
    }

    /**
     * Maps one order snapshot, merged with the order data the plugin stored for it.
     *
     * @param  array<string, mixed> $snapshot
     */
    public function fromSnapshot(array $snapshot): PdkOrder
    {
        $factor    = (float) ($snapshot['currencyFactor'] ?? 1.0);
        $taxStatus = (string) ($snapshot['taxStatus'] ?? MoneyConverter::TAX_STATUS_GROSS);
        $email     = $snapshot['customerEmail'] ?? null;
        $shipping  = $this->addressMapper->map($snapshot['shippingAddress'] ?? null, $email);
        $billing   = $this->addressMapper->map($snapshot['billingAddress'] ?? null, $email);

        if ($shipping->splitFailed) {
            Logger::warning(
                'MyParcel: could not find a house number in the shipping address; it is sent as one street',
                ['orderId' => $snapshot['id']]
            );
        }

        $shipmentPrice = null === ($snapshot['shippingCosts'] ?? null)
            ? ['price' => 0, 'vat' => 0, 'priceAfterVat' => 0]
            : $this->moneyConverter->toUnitCents($snapshot['shippingCosts'], $factor, $taxStatus);

        $base = [
            'externalIdentifier'    => $snapshot['id'],
            'referenceIdentifier'   => $snapshot['orderNumber'] ?? null,
            'orderDate'             => $snapshot['orderDateTime'] ?? null,
            'shippingAddress'       => $shipping->contactDetails,
            'billingAddress'        => $billing->contactDetails,
            'lines'                 => $this->mapLines($snapshot['lines'] ?? [], $factor, $taxStatus),
            'shipmentPrice'         => $shipmentPrice['price'],
            'shipmentVat'           => $shipmentPrice['vat'],
            'shipmentPriceAfterVat' => $shipmentPrice['priceAfterVat'],
        ];

        return new PdkOrder(array_replace(
            $base,
            $snapshot['orderData'] ?? [],
            ['shipments' => $snapshot['shipments'] ?? []]
        ));
    }

    /**
     * @param  list<array<string, mixed>> $lines
     *
     * @return list<array<string, mixed>>
     */
    private function mapLines(array $lines, float $factor, string $taxStatus): array
    {
        $mapped = [];

        foreach ($lines as $line) {
            if (self::LINE_TYPE_PRODUCT !== ($line['type'] ?? null)) {
                continue;
            }

            $mapped[] = array_merge(
                $this->moneyConverter->toUnitCents($line['price'], $factor, $taxStatus),
                [
                    'quantity' => (int) $line['quantity'],
                    'product'  => null === $line['product']
                        ? ['name' => $line['label'] ?? null, 'sku' => $line['productNumber'] ?? null, 'weight' => 0]
                        : $this->productMapper->toAttributes($line['product']),
                ]
            );
        }

        return $mapped;
    }
}
