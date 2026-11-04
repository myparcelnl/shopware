<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Product\Mapper;

use MyParcel\Shopware\Pdk\Product\ProductCustomFields;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\Base\Contract\CurrencyServiceInterface;
use MyParcelNL\Pdk\Base\Contract\WeightServiceInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;

/**
 * Maps a Shopware product to a PdkProduct.
 *
 * Shopware stores weight in kilograms and dimensions in millimeters
 * (MeasurementUnits::DEFAULT_LENGTH_UNIT). The PDK wants grams. It passes the
 * dimensions of a product on unchanged to the fulfilment product
 * (Fulfilment\Model\Product::fromPdkProduct()), whose fields the SDK documents in
 * millimeters, so the mapper sends millimeters too.
 */
final class PdkProductMapper
{
    public function __construct(
        private readonly CurrencyServiceInterface $currencyService,
        private readonly WeightServiceInterface $weightService
    ) {
    }

    /**
     * Maps a product that was read with inheritance, with its parent when it is a
     * variant. Use it for every product the PDK reads.
     *
     * The settings come from $ownCustomFields, not from the product: with
     * inheritance, Shopware merges the custom fields of the parent into those of the
     * variant, and the PDK does the parent fallback of settings itself.
     *
     * @param  array<string, array<string, mixed>> $ownCustomFields the myparcel_* custom fields that each product has itself, keyed by product id
     * @param  float                               $euroFactor      EuroCurrency::getFactor()
     */
    public function map(ProductEntity $product, array $ownCustomFields, float $euroFactor): PdkProduct
    {
        return new PdkProduct($this->buildAttributes($product, $ownCustomFields, $euroFactor));
    }

    /**
     * @param  array<string, array<string, mixed>> $ownCustomFields
     *
     * @return array<string, mixed>
     */
    private function buildAttributes(ProductEntity $product, array $ownCustomFields, float $euroFactor): array
    {
        $gross  = $product->getPrice()?->getCurrencyPrice(Defaults::CURRENCY)?->getGross() ?? 0.0;
        $parent = $product->getParent();

        return [
            'externalIdentifier' => $product->getId(),
            'sku'                => $product->getProductNumber(),
            'ean'                => $product->getEan(),
            'name'               => $product->getTranslation('name') ?? $product->getName(),
            'isDeliverable'      => ProductDefinition::TYPE_DIGITAL !== $product->getType(),
            'price'              => ['amount' => $this->currencyService->convertToCents($gross * $euroFactor)],
            'weight'             => $this->convertWeightToGrams($product->getWeight()),
            'length'             => self::roundToMillimeters($product->getLength()),
            'width'              => self::roundToMillimeters($product->getWidth()),
            'height'             => self::roundToMillimeters($product->getHeight()),
            'settings'           => ProductCustomFields::toSettings($ownCustomFields[$product->getId()] ?? []),
            'parent'             => null === $parent ? null : $this->buildAttributes($parent, $ownCustomFields, $euroFactor),
        ];
    }

    /**
     * WeightService::convertToGrams() rounds up with ceil(). Kilograms times 1000
     * has a floating point error (2.007 * 1000 is 2007.0000000000002), which ceil()
     * turns into one gram too many. Rounding the grams to 6 decimals removes that
     * error and keeps a part of a gram, which ceil() still rounds up.
     */
    private function convertWeightToGrams(?float $kilograms): int
    {
        return $this->weightService->convertToGrams(
            round(($kilograms ?? 0.0) * 1000, 6),
            WeightServiceInterface::UNIT_GRAMS
        );
    }

    private static function roundToMillimeters(?float $millimeters): int
    {
        return (int) round($millimeters ?? 0.0);
    }
}
