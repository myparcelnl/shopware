<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Product\Repository;

use MyParcel\Shopware\Pdk\Product\Contract\ProductStorageInterface;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\ProductCustomFields;
use MyParcelNL\Pdk\App\Order\Collection\PdkProductCollection;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\App\Order\Repository\AbstractPdkPdkProductRepository;
use MyParcelNL\Pdk\Settings\Model\ProductSettings;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;

/**
 * Reads Shopware products as PdkProducts and stores their MyParcel settings in the
 * product custom fields.
 */
final class PdkProductRepository extends AbstractPdkPdkProductRepository
{
    private const KEY_PREFIX = 'product_';

    public function __construct(
        StorageInterface $storage,
        private readonly ProductStorageInterface $productStorage,
        private readonly PdkProductMapper $mapper
    ) {
        parent::__construct($storage);
    }

    /**
     * Returns the product. An unknown id gives a product with only that id, so a
     * PDK action on a deleted product does not fail. Use it to get one product.
     *
     * @param  mixed $identifier
     */
    public function getProduct($identifier): PdkProduct
    {
        $productId = (string) $identifier;

        return $this->retrieve(self::KEY_PREFIX . $productId, function () use ($productId): PdkProduct {
            $snapshot = $this->productStorage->getSnapshots([$productId])[$productId] ?? null;

            return null === $snapshot
                ? new PdkProduct(['externalIdentifier' => $productId])
                : $this->mapper->fromSnapshot($snapshot);
        });
    }

    /**
     * Returns the MyParcel settings of one product. Use it when only the settings
     * are needed.
     *
     * @param  mixed $identifier
     */
    public function getProductSettings($identifier): ProductSettings
    {
        return $this->getProduct($identifier)->settings;
    }

    /**
     * Returns the products that exist. Without ids it returns none: the PDK never
     * needs every product of a shop. Use it to get the products of an order or
     * of a list.
     *
     * @param  array<mixed> $identifiers
     */
    public function getProducts(array $identifiers = []): PdkProductCollection
    {
        $ids      = array_map('strval', $identifiers);
        $products = [];

        foreach ([] === $ids ? [] : $this->productStorage->getSnapshots($ids) as $productId => $snapshot) {
            $products[] = $this->save(self::KEY_PREFIX . $productId, $this->mapper->fromSnapshot($snapshot));
        }

        return new PdkProductCollection($products);
    }

    /**
     * Stores the product's settings. Other custom fields stay as they are. Use it
     * when the PDK saves product settings.
     */
    public function update(PdkProduct $product): void
    {
        $productId = (string) $product->externalIdentifier;

        $this->productStorage->saveSettings($productId, ProductCustomFields::toCustomFields($product->settings));

        $this->save(self::KEY_PREFIX . $productId, $product);
    }
}
