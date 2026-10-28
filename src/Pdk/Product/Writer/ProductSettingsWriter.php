<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Product\Writer;

use MyParcel\Shopware\Pdk\Product\ProductCustomFields;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Writes the MyParcel settings of a product to its myparcel_* custom fields, in
 * the system scope (Context::createDefaultContext()) and the default language.
 */
final class ProductSettingsWriter
{
    /**
     * @param  EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(private readonly EntityRepository $productRepository)
    {
    }

    /**
     * Writes only the given myparcel_* custom fields; the DAL merges them into the
     * custom fields of the product, so the others stay. Use it when the PDK saves
     * product settings.
     *
     * @param  array<string, null|int|string> $customFields ProductCustomFields::toCustomFields()
     */
    public function save(string $productId, array $customFields): void
    {
        $this->productRepository->update(
            [['id' => $productId, 'customFields' => ProductCustomFields::onlyOwnKeys($customFields)]],
            Context::createDefaultContext()
        );
    }
}
