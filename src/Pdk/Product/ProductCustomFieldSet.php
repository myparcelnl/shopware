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
 * sync() needs a booted PDK, for the dynamic export* settings.
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
     * Deletes the field set, its fields (they cascade) and the values of those
     * fields on every product. Use it when the plugin is uninstalled without
     * keepUserData. Does not need a booted PDK.
     */
    public function remove(Context $context): void
    {
        $setId = Uuid::fromStringToHex(ProductCustomFields::SET_NAME);

        // The names come from the stored fields, not from the PDK settings: sync()
        // keeps the fields of settings that the PDK dropped, and their values must go too.
        $names = $this->connection->fetchFirstColumn(
            'SELECT `name` FROM `custom_field` WHERE `set_id` = :setId',
            ['setId' => Uuid::fromHexToBytes($setId)]
        );

        $this->customFieldSetRepository->delete([['id' => $setId]], $context);

        if ([] === $names) {
            return;
        }

        // product.custom_fields is translated, so the values live in
        // product_translation. JSON_REMOVE ignores paths that do not exist.
        $this->connection->executeStatement(
            sprintf(
                'UPDATE `product_translation` SET `custom_fields` = JSON_REMOVE(`custom_fields`, %s) WHERE `custom_fields` IS NOT NULL',
                implode(', ', array_fill(0, count($names), '?'))
            ),
            array_map(static fn (string $name) => '$.' . $name, $names)
        );
    }
}
