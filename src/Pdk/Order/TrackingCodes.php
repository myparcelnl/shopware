<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order;

use MyParcelNL\Pdk\Shipment\Collection\ShipmentCollection;
use MyParcelNL\Pdk\Shipment\Model\Shipment;

/**
 * The tracking codes of a Shopware order delivery, with the barcodes of the
 * MyParcel shipments. Shopware shows them in the admin and the customer account,
 * and a mail template can show them through order.deliveries.
 *
 * The field is a copy for the customer, not a source: MyParcel never reads
 * it back, and a code that the merchant removes by hand returns on the next
 * save of the order. The merchant removes the shipment itself in the MyParcel
 * tab.
 */
final class TrackingCodes
{
    /**
     * Adds the barcodes of the shipments to $existing and removes those of deleted
     * shipments. Other codes, such as those the merchant entered, stay first. A
     * return shipment is left out: its barcode does not track the parcel to the
     * customer.
     *
     * @param  list<string> $existing
     *
     * @return list<string>
     */
    public static function merge(array $existing, ShipmentCollection $shipments): array
    {
        $removed = [];
        $added   = [];

        /** @var Shipment $shipment */
        foreach ($shipments as $shipment) {
            if (null === $shipment->barcode || '' === $shipment->barcode || $shipment->isReturn) {
                continue;
            }

            if (null !== $shipment->deleted) {
                $removed[] = $shipment->barcode;
            } else {
                $added[] = $shipment->barcode;
            }
        }

        return array_values(array_unique(array_diff([...$existing, ...$added], $removed)));
    }
}
