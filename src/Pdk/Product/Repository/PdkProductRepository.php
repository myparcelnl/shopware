<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Product\Repository;

use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\ProductCustomFields;
use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcelNL\Pdk\App\Order\Collection\PdkProductCollection;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\App\Order\Repository\AbstractPdkPdkProductRepository;
use MyParcelNL\Pdk\Settings\Model\ProductSettings;
use MyParcelNL\Pdk\Storage\Contract\StorageInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads Shopware products as PdkProducts and stores their MyParcel settings in the
 * product custom fields.
 *
 * Reads run in the system scope (Context::createDefaultContext()) in the default
 * language, so they do not check the product:read privilege. The admin endpoint
 * only checks myparcel:access; the role that INT-1958 adds for it also needs
 * product:read.
 */
final class PdkProductRepository extends AbstractPdkPdkProductRepository
{
    private const KEY_PREFIX = 'product_';

    /**
     * $storage is the PDK's in-request cache, not a Shopware store.
     *
     * @param  EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        StorageInterface $storage,
        private readonly EntityRepository $productRepository,
        private readonly EuroCurrency $euroCurrency,
        private readonly PdkProductMapper $mapper,
        private readonly ProductSettingsWriter $productSettingsWriter
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

        return $this->retrieve(
            self::KEY_PREFIX . $productId,
            fn (): PdkProduct => $this->readProducts([$productId])[$productId]
                ?? new PdkProduct(['externalIdentifier' => $productId])
        );
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
        $products = [];

        foreach ($this->readProducts(array_map('strval', $identifiers)) as $productId => $product) {
            $products[] = $this->save(self::KEY_PREFIX . $productId, $product);
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

        $this->productSettingsWriter->save($productId, ProductCustomFields::toCustomFields($product->settings));

        $this->save(self::KEY_PREFIX . $productId, $product);
    }

    /**
     * Reads the products with their parents. Two reads: with inheritance for the
     * product data (a variant without its own weight has its parent's), and without
     * inheritance for the MyParcel custom fields, because the PDK needs what a
     * variant has on its own to do the parent fallback of settings itself.
     *
     * The second read also gives the parents. Shopware does not let a read of an
     * inheritance-aware entity load the parent association, and a parent is a
     * product without a parent, so inheritance does not change its data.
     *
     * @param  list<string> $productIds
     *
     * @return array<string, PdkProduct> keyed by product id; unknown ids are left out
     */
    private function readProducts(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter($productIds, [Uuid::class, 'isValid'])));

        if ([] === $ids) {
            return [];
        }

        $products = $this->search(new Criteria($ids), true);

        if (0 === $products->count()) {
            return [];
        }

        $parentIds = array_values(array_filter(array_map(
            static fn (ProductEntity $product) => $product->getParentId(),
            $products->getElements()
        )));

        $ownProducts     = $this->search(new Criteria(array_values(array_unique([...$ids, ...$parentIds]))), false);
        $ownCustomFields = [];

        /** @var ProductEntity $product */
        foreach ($ownProducts as $product) {
            $ownCustomFields[$product->getId()] = ProductCustomFields::onlyOwnKeys($product->getCustomFields());
        }

        $euroFactor = $this->euroCurrency->getFactor();
        $mapped     = [];

        foreach ($ids as $id) {
            $product = $products->get($id);

            if (null === $product) {
                continue;
            }

            $parent = null === $product->getParentId() ? null : $ownProducts->get($product->getParentId());

            if ($parent instanceof ProductEntity) {
                $product->setParent($parent);
            }

            $mapped[$id] = $this->mapper->map($product, $ownCustomFields, $euroFactor);
        }

        return $mapped;
    }

    /**
     * @return ProductCollection
     */
    private function search(Criteria $criteria, bool $considerInheritance): EntityCollection
    {
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance($considerInheritance);

        return $this->productRepository->search($criteria, $context)->getEntities();
    }
}
