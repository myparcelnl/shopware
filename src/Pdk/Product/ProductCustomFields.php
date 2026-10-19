<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Product;

use MyParcelNL\Pdk\Settings\Model\ProductSettings;
use MyParcelNL\Pdk\Types\Service\TriStateService;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentPackageType;
use MyParcelNL\Sdk\Services\Mapping\ApiMapperService;

/**
 * The mapping between PDK product settings and the product custom fields of the
 * set "myparcel_product". The installer and the product mapper both use it, so the
 * field names and value types are defined once.
 *
 * Needs a booted PDK: the export* keys come from the PDK's order option definitions.
 */
final class ProductCustomFields
{
    public const SET_NAME = 'myparcel_product';
    public const PREFIX   = 'myparcel_';

    private const TYPE_TRI_STATE    = 'triState';
    private const TYPE_INT          = 'int';
    private const TYPE_TEXT         = 'text';
    private const TYPE_PACKAGE_TYPE = 'packageType';

    /**
     * Settings that are not a plain tri-state. Every other key, including the
     * dynamic export* options, is one.
     */
    private const TYPES = [
        ProductSettings::COUNTRY_OF_ORIGIN    => self::TYPE_TEXT,
        ProductSettings::CUSTOMS_CODE         => self::TYPE_TEXT,
        ProductSettings::DROP_OFF_DELAY       => self::TYPE_INT,
        ProductSettings::FIT_IN_DIGITAL_STAMP => self::TYPE_INT,
        ProductSettings::FIT_IN_MAILBOX       => self::TYPE_INT,
        ProductSettings::PACKAGE_TYPE         => self::TYPE_PACKAGE_TYPE,
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
            $values[$key] = self::fromField(self::type($key), $customFields[self::fieldName($key)] ?? null);
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
                in_array(self::type($key), [self::TYPE_TEXT, self::TYPE_PACKAGE_TYPE], true) => (string) $value,
                default => (int) $value,
            };
        }

        return $fields;
    }

    /**
     * Keeps only the keys of this set, so other plugins' custom fields stay out of
     * snapshots and writes.
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
     * The custom field payloads for the installer, without ids.
     *
     * @return list<array{name: string, type: string, config: array<string, mixed>}>
     */
    public static function fieldDefinitions(): array
    {
        $definitions = [];

        foreach (self::keys() as $position => $key) {
            $label = ['en-GB' => 'MyParcel: ' . $key];
            $base  = ['label' => $label, 'customFieldPosition' => $position + 1];

            $definitions[] = match (self::type($key)) {
                self::TYPE_TEXT         => [
                    'name'   => self::fieldName($key),
                    'type'   => 'text',
                    'config' => $base + ['componentName' => 'sw-field', 'type' => 'text', 'customFieldType' => 'text'],
                ],
                self::TYPE_INT          => [
                    'name'   => self::fieldName($key),
                    'type'   => 'int',
                    'config' => $base + ['componentName' => 'sw-field', 'type' => 'number', 'numberType' => 'int', 'customFieldType' => 'number'],
                ],
                self::TYPE_PACKAGE_TYPE => [
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

    private static function type(string $key): string
    {
        return self::TYPES[$key] ?? self::TYPE_TRI_STATE;
    }

    private static function fromField(string $type, mixed $value): int|string
    {
        if (null === $value || '' === (is_string($value) ? trim($value) : $value)) {
            return TriStateService::INHERIT;
        }

        return match ($type) {
            self::TYPE_TEXT, self::TYPE_PACKAGE_TYPE => trim((string) $value),
            self::TYPE_INT                           => is_numeric($value) ? (int) $value : TriStateService::INHERIT,
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
            ['label' => ['en-GB' => 'Inherit', 'de-DE' => 'Übernehmen', 'nl-NL' => 'Overnemen'], 'value' => TriStateService::INHERIT],
            ['label' => ['en-GB' => 'Off', 'de-DE' => 'Aus', 'nl-NL' => 'Uit'], 'value' => TriStateService::DISABLED],
            ['label' => ['en-GB' => 'On', 'de-DE' => 'An', 'nl-NL' => 'Aan'], 'value' => TriStateService::ENABLED],
        ];
    }

    /**
     * Package types come from the SDK, never from a list in the plugin.
     *
     * @return list<array{label: array<string, string>, value: string}>
     */
    private static function packageTypeOptions(): array
    {
        $mapper  = ApiMapperService::forPackageType();
        $options = [];

        foreach (RefShipmentPackageType::getAllowableEnumValues() as $id) {
            $name = $mapper->legacyNameFromId((int) $id);

            if (null !== $name) {
                $options[] = ['label' => ['en-GB' => $name], 'value' => $name];
            }
        }

        return $options;
    }
}
