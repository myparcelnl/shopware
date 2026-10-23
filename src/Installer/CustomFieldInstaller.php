<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Installer;

use MyParcel\Shopware\Pdk\Product\ProductCustomFields;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Creates and removes the custom field set "myparcel_product". Fixed ids derived
 * from the names make install() an upsert, so update() can run it again to add
 * fields for new PDK settings.
 *
 * Needs a booted PDK, for the dynamic export* settings.
 */
final class CustomFieldInstaller
{
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository
    ) {
    }

    /**
     * Creates the field set, or adds the fields it does not have yet. Removes the
     * relation to product, so the fields do not show in the product page's "Custom
     * fields" card (see ProductCustomFields::setDefinition()).
     */
    public function install(Context $context): void
    {
        $set                 = ProductCustomFields::setDefinition();
        $set['id']           = Uuid::fromStringToHex(ProductCustomFields::SET_NAME);
        $set['customFields'] = array_map(
            static fn (array $field) => ['id' => Uuid::fromStringToHex($field['name'])] + $field,
            $set['customFields']
        );

        $this->customFieldSetRepository->upsert([$set], $context);

        // Earlier development builds related the set to product. An upsert without
        // relations keeps an existing one, so delete it by its fixed id. A missing
        // relation is not an error for the DAL.
        $this->customFieldSetRelationRepository->delete(
            [['id' => Uuid::fromStringToHex(ProductCustomFields::SET_NAME . '.product')]],
            $context
        );
    }

    /**
     * Deletes the field set. Its fields cascade.
     */
    public function uninstall(Context $context): void
    {
        $this->customFieldSetRepository->delete([['id' => Uuid::fromStringToHex(ProductCustomFields::SET_NAME)]], $context);
    }
}
