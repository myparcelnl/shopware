<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Product\Mapper;

use MyParcel\Shopware\Pdk\Product\ProductCustomFields;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\Base\Contract\CurrencyServiceInterface;
use MyParcelNL\Pdk\Base\Contract\WeightServiceInterface;

/**
 * Maps a Shopware product snapshot to a PdkProduct.
 *
 * Shopware stores weight in kilograms and dimensions in millimeters. The PDK
 * expects grams and, like the shipment dimensions, centimeters.
 */
final class PdkProductMapper
{
    private const TYPE_DIGITAL = 'digital';

    public function __construct(
        private readonly CurrencyServiceInterface $currencyService,
        private readonly WeightServiceInterface $weightService
    ) {
    }

    /**
     * Maps a product snapshot, with its parent when it is a variant.
     *
     * @param  array<string, mixed> $snapshot
     */
    public function fromSnapshot(array $snapshot): PdkProduct
    {
        return new PdkProduct($this->toAttributes($snapshot));
    }

    /**
     * The PdkProduct attributes of a snapshot. The order mapper puts these in its
     * order lines.
     *
     * @param  array<string, mixed> $snapshot
     *
     * @return array<string, mixed>
     */
    public function toAttributes(array $snapshot): array
    {
        return [
            'externalIdentifier' => $snapshot['id'],
            'sku'                => $snapshot['productNumber'] ?? null,
            'ean'                => $snapshot['ean'] ?? null,
            'name'               => $snapshot['name'] ?? null,
            'isDeliverable'      => self::TYPE_DIGITAL !== ($snapshot['type'] ?? null),
            'price'              => [
                'amount'   => $this->currencyService->convertToCents((float) ($snapshot['priceGross'] ?? 0)),
                'currency' => 'EUR',
            ],
            'weight'             => $this->weightService->convertToGrams(
                (float) ($snapshot['weight'] ?? 0),
                WeightServiceInterface::UNIT_KILOGRAMS
            ),
            'length'             => self::toCentimeters($snapshot['length'] ?? null),
            'width'              => self::toCentimeters($snapshot['width'] ?? null),
            'height'             => self::toCentimeters($snapshot['height'] ?? null),
            'settings'           => ProductCustomFields::toSettings($snapshot['customFields'] ?? []),
            'parent'             => isset($snapshot['parent']) ? $this->toAttributes($snapshot['parent']) : null,
        ];
    }

    private static function toCentimeters(null|float|int $millimeters): int
    {
        return (int) round(((float) ($millimeters ?? 0)) / 10);
    }
}
