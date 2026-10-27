<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Product\Storage;

use MyParcel\Shopware\Pdk\Product\Contract\ProductStorageInterface;
use MyParcel\Shopware\Pdk\Product\ProductCustomFields;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads products in the system default language.
 *
 * Two reads: with inheritance for the product data (a variant without its own weight
 * has its parent's), and without inheritance for the MyParcel custom fields. The PDK
 * does the parent fallback of settings itself, so it needs what the variant has on
 * its own.
 */
final class ShopwareProductStorage implements ProductStorageInterface
{
    /**
     * @param  EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(private readonly EntityRepository $productRepository)
    {
    }

    public function getSnapshots(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter($productIds, [Uuid::class, 'isValid'])));

        if ([] === $ids) {
            return [];
        }

        $products  = $this->load($ids, true);
        $parentIds = array_values(array_unique(array_filter(array_map(
            static fn (ProductEntity $product) => $product->getParentId(),
            $products->getElements()
        ))));
        $missing   = array_values(array_diff($parentIds, $ids));

        if ([] !== $missing) {
            $products->merge($this->load($missing, true));
        }

        $ownFields = [];

        foreach ($this->load(array_values(array_unique([...$ids, ...$parentIds])), false) as $product) {
            $ownFields[$product->getId()] = ProductCustomFields::onlyOwnKeys($product->getCustomFields());
        }

        $snapshots = [];

        foreach ($ids as $id) {
            $product = $products->get($id);

            if (null === $product) {
                continue;
            }

            $parent         = $product->getParentId() ? $products->get($product->getParentId()) : null;
            $snapshots[$id] = $this->snapshot(
                $product,
                $ownFields,
                null === $parent ? null : $this->snapshot($parent, $ownFields, null)
            );
        }

        return $snapshots;
    }

    public function saveSettings(string $productId, array $customFields): void
    {
        $this->productRepository->update(
            [['id' => $productId, 'customFields' => ProductCustomFields::onlyOwnKeys($customFields)]],
            Context::createDefaultContext()
        );
    }

    /**
     * @param  list<string> $ids
     */
    private function load(array $ids, bool $considerInheritance): ProductCollection
    {
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance($considerInheritance);

        /** @var ProductCollection $products */
        $products = $this->productRepository->search(new Criteria($ids), $context)->getEntities();

        return $products;
    }

    /**
     * @param  array<string, array<string, mixed>> $ownFields
     * @param  null|array<string, mixed>           $parent
     *
     * @return array<string, mixed>
     */
    private function snapshot(ProductEntity $product, array $ownFields, ?array $parent): array
    {
        return [
            'id'            => $product->getId(),
            'parentId'      => $product->getParentId(),
            'productNumber' => $product->getProductNumber(),
            'ean'           => $product->getEan(),
            'name'          => $product->getTranslation('name') ?? $product->getName(),
            'priceGross'    => $product->getPrice()?->getCurrencyPrice(Defaults::CURRENCY)?->getGross(),
            'weight'        => $product->getWeight(),
            'width'         => $product->getWidth(),
            'height'        => $product->getHeight(),
            'length'        => $product->getLength(),
            'type'          => $product->getType(),
            'customFields'  => $ownFields[$product->getId()] ?? [],
            'parent'        => $parent,
        ];
    }
}
