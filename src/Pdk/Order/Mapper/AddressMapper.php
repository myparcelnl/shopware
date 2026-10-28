<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Mapper;

use MyParcelNL\Pdk\Facade\Pdk;

/**
 * Maps a Shopware order address snapshot to PDK contact details.
 *
 * Shopware has one street field. For the countries where MyParcel needs a separate
 * house number (countriesWithSeparateAddressFields), StreetSplitter splits the
 * street. Some merchants use additionalAddressLine1 as the house number field, so
 * when the street alone has no number, the mapper tries street plus that line, and
 * keeps that split only when the street part stays the same as the street field.
 * There is no configurable template: the old plugin's template was a constant
 * source of support tickets.
 */
final class AddressMapper
{
    public function __construct(private readonly StreetSplitter $streetSplitter)
    {
    }

    /**
     * Maps one address. A null address gives contact details with only the e-mail.
     *
     * @param  null|array<string, mixed> $address
     */
    public function map(?array $address, ?string $email): AddressMapResult
    {
        if (null === $address) {
            return new AddressMapResult(array_filter(['email' => $email], static fn ($value) => null !== $value), false);
        }

        $street     = trim((string) ($address['street'] ?? ''));
        $line1      = trim((string) ($address['additionalAddressLine1'] ?? ''));
        $line2      = trim((string) ($address['additionalAddressLine2'] ?? ''));
        $cc         = strtoupper((string) ($address['countryIso'] ?? ''));
        $extraLines = [$line1, $line2];
        $streetPart = ['street' => $street, 'number' => null, 'numberSuffix' => null, 'boxNumber' => null];
        $failed     = false;

        if (in_array($cc, Pdk::get('countriesWithSeparateAddressFields'), true)) {
            $split = $this->streetSplitter->split($street, $cc);

            if (null === $split && '' !== $line1) {
                $split = $this->streetSplitter->split($street . ' ' . $line1, $cc);

                // Accept the split only when line 1 holds just the house number. Line 1
                // "Appartement 3" would otherwise give street "Teststraat Appartement".
                if (null !== $split && 0 !== strcasecmp(trim($split['street']), $street)) {
                    $split = null;
                }

                if (null !== $split) {
                    $extraLines = [$line2];
                }
            }

            if (null === $split) {
                $failed = true;
            } else {
                $streetPart = $split;
            }
        }

        $additionalInfo = implode(' ', array_filter($extraLines, static fn (string $line) => '' !== $line));

        return new AddressMapResult(
            array_merge($streetPart, [
                'streetAdditionalInfo' => '' === $additionalInfo ? null : $additionalInfo,
                'postalCode'           => $address['zipcode'] ?? null,
                'city'                 => $address['city'] ?? null,
                'cc'                   => '' === $cc ? null : $cc,
                'region'               => $address['stateName'] ?? null,
                'person'               => trim(($address['firstName'] ?? '') . ' ' . ($address['lastName'] ?? '')),
                'company'              => $address['company'] ?? null,
                'phone'                => $address['phoneNumber'] ?? null,
                'email'                => $email,
            ]),
            $failed
        );
    }
}
