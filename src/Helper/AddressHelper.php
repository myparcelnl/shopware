<?php

namespace Kiener\KienerMyParcel\Helper;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

class AddressHelper
{
    private const RETURN_TYPE_STREET = 'street';
    private const RETURN_TYPE_HOUSE_NUMBER = 'house_number';
    private const RETURN_TYPE_HOUSE_NUMBER_EXT = 'house_number_ext';

    public const PREG_MATCH_ADDRESS_NL =
        '~(?P<street>.*?)' .              // The rest belongs to the street
        '\s?' .                           // Separator between street and number
        '(?P<number>\d{1,4})' .           // Number can contain a maximum of 4 numbers
        '[/\s\-]{0,2}' .                  // Separators between number and addition
        '(?P<number_suffix>' .
        '[a-zA-Z]{1}\d{1,3}|' .           // Numbers suffix starts with a letter followed by numbers or
        '-\d{1,4}|' .                     // starts with - and has up to 4 numbers or
        '\d{2}\w{1,2}|' .                 // starts with 2 numbers followed by letters or
        '[a-zA-Z]{1}[a-zA-Z\s]{0,3}' .    // has up to 4 letters with a space
        ')?$~';

    public const PREG_MATCH_ADDRESS_BE =
        '~(?P<street>.*?)\s(?P<street_suffix>(?P<number>\S{1,8})\s?(?P<box_separator>bus?)?\s?(?P<box_number>\d{0,8}$))$~';

    /**
     * Get the house number or its addition from a full street.
     *
     * @param $fullStreet
     * @param string $returnType
     * @param string $countryCode
     * @return string
     */
    private static function getAddressParts($fullStreet, $returnType, $countryCode = 'nl'): string
    {
        // Variables
        $street = '';
        $houseNumber = null;
        $houseNumberExtension = null;

        // Get street, house number and extension via preg match
        preg_match(
            $countryCode === 'nl' ? static::PREG_MATCH_ADDRESS_NL : static::PREG_MATCH_ADDRESS_BE, $fullStreet,
            $matches
        );

        if (isset($matches['street']) && $matches['street'] !== '') {
            $street = $matches['street'];
        }

        if (isset($matches['number']) && is_numeric($matches['number'])) {
            $houseNumber = $matches['number'];
        }

        if (isset($matches['number_suffix'])) {
            $houseNumberExtension = $matches['number_suffix'];
        }

        // Return value depending on requested return type
        if ($returnType === static::RETURN_TYPE_HOUSE_NUMBER) {
            return (string) $houseNumber;
        }

        if ($returnType === static::RETURN_TYPE_HOUSE_NUMBER_EXT) {
            return (string) $houseNumberExtension;
        }

        return (string) $street;
    }

    /**
     * Get a parsed part of the address.
     *
     * @param OrderAddressEntity $address
     * @return array
     */
    public static function parseAddress($address): array
    {
        // Variables
        $street = $address->getStreet();
        $houseNumber = static::getAddressParts($street, static::RETURN_TYPE_HOUSE_NUMBER);
        $houseNumberAddition = static::getAddressParts($street, static::RETURN_TYPE_HOUSE_NUMBER_EXT);
        $street = static::getAddressParts($street, static::RETURN_TYPE_STREET);

        if ($street === null || $street === '') {
            $street = $address->getStreet();
        }

        return [
            'street' => $street,
            'houseNumber' => $houseNumber,
            'houseNumberAddition' => $houseNumberAddition,
        ];
    }
}