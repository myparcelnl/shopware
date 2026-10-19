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
    public function __construct(private readonly EntityRepository $customFieldSetRepository)
    {
    }

    /**
     * Creates the field set, or adds the fields it does not have yet.
     */
    public function install(Context $context): void
    {
        $this->customFieldSetRepository->upsert([[
            'id'           => Uuid::fromStringToHex(ProductCustomFields::SET_NAME),
            'name'         => ProductCustomFields::SET_NAME,
            'active'       => true,
            'config'       => [
                'label' => [
                    'en-GB' => 'MyParcel (fill in the default language)',
                    'de-DE' => 'MyParcel (in der Standardsprache ausfüllen)',
                    'nl-NL' => 'MyParcel (vul in in de standaardtaal)',
                ],
            ],
            'relations'    => [[
                'id'         => Uuid::fromStringToHex(ProductCustomFields::SET_NAME . '.product'),
                'entityName' => 'product',
            ]],
            'customFields' => array_map(
                static fn (array $field) => ['id' => Uuid::fromStringToHex($field['name'])] + $field,
                ProductCustomFields::fieldDefinitions()
            ),
        ]], $context);
    }

    /**
     * Deletes the field set. Its fields and relation cascade.
     */
    public function uninstall(Context $context): void
    {
        $this->customFieldSetRepository->delete([['id' => Uuid::fromStringToHex(ProductCustomFields::SET_NAME)]], $context);
    }
}
