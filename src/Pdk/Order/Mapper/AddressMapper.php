<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Mapper;

use MyParcelNL\Pdk\Facade\Pdk;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

/**
 * Maps a Shopware order address to PDK contact details.
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
     * Use it for the shipping and the billing address of an order.
     */
    public function map(?OrderAddressEntity $address, ?string $email): AddressMapResult
    {
        if (null === $address) {
            return new AddressMapResult(array_filter(['email' => $email], static fn ($value) => null !== $value), false);
        }

        $street     = trim($address->getStreet());
        $line1      = trim((string) $address->getAdditionalAddressLine1());
        $line2      = trim((string) $address->getAdditionalAddressLine2());
        $cc         = strtoupper((string) $address->getCountry()?->getIso());
        $extraLines = [$line1, $line2];
        $streetPart = ['street' => $street, 'number' => null, 'numberSuffix' => null, 'boxNumber' => null];
        $failed     = false;

        if (in_array($cc, Pdk::get('countriesWithSeparateAddressFields'), true)) {
            $split = $this->streetSplitter->split($street, $cc);

            if (null === $split) {
                $split = $this->splitWithLine1($street, $line1, $cc);

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
                'postalCode'           => $address->getZipcode(),
                'city'                 => $address->getCity(),
                'cc'                   => '' === $cc ? null : $cc,
                'region'               => $address->getCountryState()?->getName(),
                'person'               => trim($address->getFirstName() . ' ' . $address->getLastName()),
                'company'              => $address->getCompany(),
                'phone'                => $address->getPhoneNumber(),
                'email'                => $email,
            ]),
            $failed
        );
    }

    /**
     * Tells if additionalAddressLine1 holds the house number of the street, by
     * the same rule as map(). Use it where the address goes somewhere other than
     * the order, such as the delivery options widget, so that it gets the same
     * house number as the order. Call it only after the PDK has booted.
     */
    public function houseNumberInLine1(string $street, string $line1, string $cc): bool
    {
        $street = trim($street);
        $cc     = strtoupper($cc);

        return in_array($cc, Pdk::get('countriesWithSeparateAddressFields'), true)
            && null === $this->streetSplitter->split($street, $cc)
            && null !== $this->splitWithLine1($street, trim($line1), $cc);
    }

    /**
     * Splits street plus line 1. Accepts the split only when line 1 holds just the
     * house number: line 1 "Appartement 3" would otherwise give street
     * "Teststraat Appartement".
     *
     * @return null|array{street: string, number: string, numberSuffix: ?string, boxNumber: ?string}
     */
    private function splitWithLine1(string $street, string $line1, string $cc): ?array
    {
        if ('' === $line1) {
            return null;
        }

        $split = $this->streetSplitter->split($street . ' ' . $line1, $cc);

        if (null === $split || 0 !== strcasecmp(trim($split['street']), $street)) {
            return null;
        }

        return $split;
    }
}
