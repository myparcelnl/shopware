<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Product;

use MyParcelNL\Pdk\Settings\Model\ProductSettings;
use MyParcelNL\Pdk\Types\Service\TriStateService;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentPackageType;
use MyParcelNL\Sdk\Services\Mapping\ApiMapperService;

/**
 * The mapping between PDK product settings and the product custom fields of the
 * set "myparcel_product". ProductCustomFieldSet and the product mapper both use it,
 * so the field names and value types are defined once.
 *
 * Needs a booted PDK: the export* keys come from the PDK's order option definitions.
 */
final class ProductCustomFields
{
    public const SET_NAME = 'myparcel_product';
    public const PREFIX   = 'myparcel_';

    private const FIELD_TRI_STATE    = 'triState';
    private const FIELD_INT          = 'int';
    private const FIELD_TEXT         = 'text';
    private const FIELD_PACKAGE_TYPE = 'packageType';

    private const INHERIT_OPTION = ['label' => ['en-GB' => 'Inherit', 'de-DE' => 'Übernehmen', 'nl-NL' => 'Overnemen'], 'value' => TriStateService::INHERIT];

    /**
     * The field type for each cast type of ProductSettings::$casts.
     */
    private const FIELD_TYPE_BY_CAST = [
        'int'                         => self::FIELD_INT,
        TriStateService::TYPE_STRICT  => self::FIELD_TRI_STATE,
        TriStateService::TYPE_COERCED => self::FIELD_TEXT,
    ];

    /**
     * A copy of the casts in ProductSettings::$casts that are not
     * TriStateService::TYPE_STRICT. Every other key, the dynamic export* options
     * included, is TYPE_STRICT. The PDK keeps the casts protected, so the plugin
     * cannot read them. ProductCustomFieldsTest reads them with reflection and
     * fails when this copy differs from the PDK.
     */
    private const CASTS = [
        ProductSettings::COUNTRY_OF_ORIGIN    => TriStateService::TYPE_COERCED,
        ProductSettings::CUSTOMS_CODE         => TriStateService::TYPE_COERCED,
        ProductSettings::DROP_OFF_DELAY       => 'int',
        ProductSettings::FIT_IN_DIGITAL_STAMP => 'int',
        ProductSettings::FIT_IN_MAILBOX       => 'int',
        ProductSettings::PACKAGE_TYPE         => TriStateService::TYPE_COERCED,
    ];

    /**
     * Every product setting key, in the order of ProductSettings.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_values(array_filter(
            array_keys((new ProductSettings())->getAttributes()),
            static fn (string $key) => 'id' !== $key
        ));
    }

    /**
     * The custom field name of a setting, e.g. fitInMailbox becomes myparcel_fit_in_mailbox.
     */
    public static function fieldName(string $key): string
    {
        return self::PREFIX . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
    }

    /**
     * Builds product settings from custom field values. A missing, empty or invalid
     * value becomes "inherit".
     *
     * @param  array<string, mixed> $customFields
     */
    public static function toSettings(array $customFields): ProductSettings
    {
        $values = [];

        foreach (self::keys() as $key) {
            $values[$key] = self::castFieldValue(self::getFieldType($key), $customFields[self::fieldName($key)] ?? null);
        }

        return new ProductSettings($values);
    }

    /**
     * The custom field values for these settings. "Inherit" becomes null, so the
     * product falls back to its parent and to the plugin settings.
     *
     * @return array<string, null|int|string>
     */
    public static function toCustomFields(ProductSettings $settings): array
    {
        $fields = [];

        foreach (self::keys() as $key) {
            $value = $settings->getAttribute($key);

            $fields[self::fieldName($key)] = match (true) {
                null === $value, TriStateService::INHERIT === $value, (string) TriStateService::INHERIT === $value => null,
                in_array(self::getFieldType($key), [self::FIELD_TEXT, self::FIELD_PACKAGE_TYPE], true) => (string) $value,
                default => (int) $value,
            };
        }

        return $fields;
    }

    /**
     * Keeps only the keys of this set, so the custom fields of other plugins stay out
     * of the product settings and of the writes.
     *
     * @param  null|array<string, mixed> $customFields
     *
     * @return array<string, mixed>
     */
    public static function onlyOwnKeys(?array $customFields): array
    {
        return array_filter(
            $customFields ?? [],
            static fn (string $name) => str_starts_with($name, self::PREFIX),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * The custom field set payload for ProductCustomFieldSet, without ids.
     *
     * The set has no relation to an entity, so Shopware does not show its fields in
     * the "Custom fields" card of the product page. That is all the missing relation
     * does. The product page still loads every custom field of the product, and
     * when any custom field changes, the admin sends the whole customFields object
     * on save, with the MyParcel values in it. So a save of the product page can
     * write old MyParcel values back; INT-1960 decides how the MyParcel tab
     * prevents that. Shopware stores and types the values without the relation,
     * because it reads the type of a custom field from its name.
     *
     * @return array{name: string, active: bool, config: array<string, mixed>, customFields: list<array{name: string, type: string, config: array<string, mixed>}>}
     */
    public static function setDefinition(): array
    {
        return [
            'name'         => self::SET_NAME,
            'active'       => true,
            'config'       => [
                'label' => [
                    'en-GB' => 'MyParcel product settings (managed by the plugin)',
                    'de-DE' => 'MyParcel-Produkteinstellungen (vom Plugin verwaltet)',
                    'nl-NL' => 'MyParcel-productinstellingen (beheerd door de plugin)',
                ],
            ],
            'customFields' => self::fieldDefinitions(),
        ];
    }

    /**
     * The custom field payloads for ProductCustomFieldSet, without ids.
     *
     * @return list<array{name: string, type: string, config: array<string, mixed>}>
     */
    public static function fieldDefinitions(): array
    {
        $definitions = [];

        foreach (self::keys() as $position => $key) {
            $label = ['en-GB' => 'MyParcel: ' . $key];
            $base  = ['label' => $label, 'customFieldPosition' => $position + 1];

            $definitions[] = match (self::getFieldType($key)) {
                self::FIELD_TEXT         => [
                    'name'   => self::fieldName($key),
                    'type'   => 'text',
                    'config' => $base + ['componentName' => 'sw-field', 'type' => 'text', 'customFieldType' => 'text'],
                ],
                self::FIELD_INT          => [
                    'name'   => self::fieldName($key),
                    'type'   => 'int',
                    'config' => $base + ['componentName' => 'sw-field', 'type' => 'number', 'numberType' => 'int', 'customFieldType' => 'number'],
                ],
                self::FIELD_PACKAGE_TYPE => [
                    'name'   => self::fieldName($key),
                    'type'   => 'select',
                    'config' => $base + ['componentName' => 'sw-single-select', 'customFieldType' => 'select', 'options' => self::packageTypeOptions()],
                ],
                default                 => [
                    'name'   => self::fieldName($key),
                    'type'   => 'select',
                    'config' => $base + ['componentName' => 'sw-single-select', 'customFieldType' => 'select', 'options' => self::triStateOptions()],
                ],
            };
        }

        return $definitions;
    }

    /**
     * The field type of a setting, from its PDK cast. The package type is a coerced
     * tri-state like the text fields, but it has a fixed list of values, so it gets
     * a select.
     */
    private static function getFieldType(string $key): string
    {
        if (ProductSettings::PACKAGE_TYPE === $key) {
            return self::FIELD_PACKAGE_TYPE;
        }

        return self::FIELD_TYPE_BY_CAST[self::CASTS[$key] ?? TriStateService::TYPE_STRICT];
    }

    private static function castFieldValue(string $type, mixed $value): int|string
    {
        if (null === $value || '' === (is_string($value) ? trim($value) : $value)) {
            return TriStateService::INHERIT;
        }

        return match ($type) {
            self::FIELD_TEXT, self::FIELD_PACKAGE_TYPE => (string) TriStateService::INHERIT === trim((string) $value)
                ? TriStateService::INHERIT
                : trim((string) $value),
            self::FIELD_INT                           => is_numeric($value) ? (int) $value : TriStateService::INHERIT,
            default                                  => is_numeric($value) && in_array((int) $value, [TriStateService::INHERIT, TriStateService::DISABLED, TriStateService::ENABLED], true)
                ? (int) $value
                : TriStateService::INHERIT,
        };
    }

    /**
     * @return list<array{label: array<string, string>, value: int}>
     */
    private static function triStateOptions(): array
    {
        return [
            self::INHERIT_OPTION,
            ['label' => ['en-GB' => 'Off', 'de-DE' => 'Aus', 'nl-NL' => 'Uit'], 'value' => TriStateService::DISABLED],
            ['label' => ['en-GB' => 'On', 'de-DE' => 'An', 'nl-NL' => 'Aan'], 'value' => TriStateService::ENABLED],
        ];
    }

    /**
     * Inherit, then the package types. Package types come from the SDK, never from
     * a list in the plugin.
     *
     * @return list<array{label: array<string, string>, value: int|string}>
     */
    private static function packageTypeOptions(): array
    {
        $mapper  = ApiMapperService::forPackageType();
        $options = [self::INHERIT_OPTION];

        foreach (RefShipmentPackageType::getAllowableEnumValues() as $id) {
            $name = $mapper->legacyNameFromId((int) $id);

            if (null !== $name) {
                $options[] = ['label' => ['en-GB' => $name], 'value' => $name];
            }
        }

        return $options;
    }
}
