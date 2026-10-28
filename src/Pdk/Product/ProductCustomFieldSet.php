<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Product;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;

/**
 * The custom field set "myparcel_product", which holds the MyParcel settings of
 * the products. Fixed ids derived from the names make sync() an upsert, so the
 * plugin update can run it again.
 *
 * The plugin lifecycle (install, update, uninstall) runs this, not a PDK
 * migration. The set is Shopware data, the same as the tables of the Shopware
 * migrations in src/Migration, and the plugin does not run the PDK installer yet
 * (INT-2154).
 *
 * Needs a booted PDK, for the dynamic export* settings.
 */
final class ProductCustomFieldSet
{
    /**
     * @param  EntityRepository<CustomFieldSetCollection> $customFieldSetRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly Connection $connection
    ) {
    }

    /**
     * Creates the field set, or brings it in line with the plugin: adds a field for
     * each new PDK setting and resets the labels and the config of the existing
     * fields, also when a merchant edited them. Fields of settings that the PDK
     * dropped stay. Use it when the plugin is installed or updated.
     */
    public function sync(Context $context): void
    {
        $set                 = ProductCustomFields::setDefinition();
        $set['id']           = Uuid::fromStringToHex(ProductCustomFields::SET_NAME);
        $set['customFields'] = array_map(
            static fn (array $field) => ['id' => Uuid::fromStringToHex($field['name'])] + $field,
            $set['customFields']
        );

        $this->customFieldSetRepository->upsert([$set], $context);
    }

    /**
     * Deletes the field set, its fields (they cascade) and the myparcel_* values of
     * every product. Use it when the plugin is uninstalled without keepUserData.
     */
    public function remove(Context $context): void
    {
        $this->customFieldSetRepository->delete([['id' => Uuid::fromStringToHex(ProductCustomFields::SET_NAME)]], $context);

        // product.custom_fields is translated, so the values live in
        // product_translation. JSON_REMOVE ignores paths that do not exist.
        $paths = array_map(
            static fn (string $key) => sprintf("'$.%s'", ProductCustomFields::fieldName($key)),
            ProductCustomFields::keys()
        );

        $this->connection->executeStatement(sprintf(
            'UPDATE `product_translation` SET `custom_fields` = JSON_REMOVE(`custom_fields`, %s) WHERE `custom_fields` IS NOT NULL',
            implode(', ', $paths)
        ));
    }
}
