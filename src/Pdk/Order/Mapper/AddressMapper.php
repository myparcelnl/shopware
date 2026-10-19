<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Mapper;

use ErrorException;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Sdk\Helper\SplitStreet;
use Throwable;

/**
 * Maps a Shopware order address snapshot to PDK contact details.
 *
 * Shopware has one street field. For the countries where MyParcel needs a separate
 * house number (countriesWithSeparateAddressFields), the street is split with the
 * SDK. Some merchants use additionalAddressLine1 as the house number field, so when
 * the street alone has no number, the mapper tries street plus that line. There is
 * no configurable template: the old plugin's template was a constant source of
 * support tickets.
 */
final class AddressMapper
{
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
            $split = $this->split($street, $cc);

            if (null === $split && '' !== $line1) {
                $split = $this->split($street . ' ' . $line1, $cc);

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

    /**
     * SplitStreet raises PHP warnings (undefined array keys) before it throws on a
     * Belgian street it cannot match. Turn those into an exception, so a failed split
     * is one code path in debug and production mode alike.
     *
     * @return null|array{street: string, number: string, numberSuffix: ?string, boxNumber: ?string}
     */
    private function split(string $street, string $cc): ?array
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $fullStreet = SplitStreet::splitStreet($street, $cc, $cc);
        } catch (Throwable) {
            return null;
        } finally {
            restore_error_handler();
        }

        if (null === $fullStreet->getNumber()) {
            return null;
        }

        return [
            'street'       => $fullStreet->getStreet(),
            'number'       => (string) $fullStreet->getNumber(),
            'numberSuffix' => $fullStreet->getNumberSuffix() ?: null,
            'boxNumber'    => $fullStreet->getBoxNumber() ?: null,
        ];
    }
}
