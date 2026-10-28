<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Mapper;

/**
 * Splits a Dutch or Belgian street line into street, house number, number suffix
 * and box number.
 *
 * The rules and regular expressions are ported from the SDK's SplitStreet and
 * ValidateStreet, which are marked @internal and legacy. This port checks every
 * match before it reads a group, so an address that does not match gives null and
 * never a PHP warning.
 */
final class StreetSplitter
{
    /**
     * Ported from ValidateStreet::SPLIT_STREET_REGEX_NL. Reads from right to left:
     * the number has at most 5 digits, the suffix at most 6 characters.
     */
    private const REGEX_NL = '~(?P<street>.{1,78}?)\s(?P<number>\d{1,5})[/\s\-]{0,2}(?P<number_suffix>[a-z][/\-\da-z]{1,5}|-\d{1,4}|(?=.{2,6}$)\d{1,6}[/\-a-z]{1,5}|[a-z][a-z\s]{0,5})?$~i';

    /**
     * Ported from ValidateStreet::SPLIT_STREET_REGEX_BE. The J flag allows the
     * duplicate group names: the empty box_separator and box_number groups at the
     * start make sure both keys exist in every match.
     */
    private const REGEX_BE = '~(?P<box_separator>)(?P<box_number>)(?P<street>.*?)\s(?P<street_suffix>(?P<number>[0-9\-]{0,7}[0-9])(?P<number_suffix>[A-z]{0,4})\s?(?P<box_separator>(?i:bte|box|boîte|bus|Bus|\/|-|B.+)|\,\s+)*\s?(?P<box_number>[0-9A-z]{0,7}[0-9])?\s?(?:(?P<number_suffix>[A-z]{1,4}$)|))?$~J';

    /**
     * Belgian box separators that are written as "bus" before the second match.
     */
    private const BOX_SEPARATORS = ['boîte', 'box', 'bte', 'Bus'];

    /**
     * A slash, a dash or a B directly before a digit also separates a Belgian box number.
     */
    private const BOX_SEPARATORS_BEFORE_DIGIT = ['\/', '-', 'B'];

    /**
     * Dutch number suffix words and their PostNL abbreviation.
     */
    private const NUMBER_SUFFIX_ABBREVIATIONS = [
        'apartment'  => '',
        'gedempte'   => 'GED',
        'groot'      => 'GRT',
        'grote'      => 'GRT',
        'greate'     => 'GRT',
        'noordzijde' => 'NZ',
        'oostzijde'  => 'OZ',
        'zuidzijde'  => 'ZZ',
        'westzijde'  => 'WZ',
        'noord'      => 'N',
        'oost'       => 'O',
        'zuid'       => 'Z',
        'west'       => 'W',
        'hoog'       => 'HG',
        'hoge'       => 'HG',
        'hege'       => 'HG',
        'kleine'     => 'KL',
        'klein'      => 'KL',
        'korte'      => 'K',
        'kort'       => 'K',
        'koarte'     => 'K',
        'koart'      => 'K',
        'kromme'     => 'KR',
        'krom'       => 'KR',
        'laag'       => 'LG',
        'lage'       => 'LG',
        'lege'       => 'LG',
        'lange'      => 'L',
        'lang'       => 'L',
        'nieuwe'     => 'NW',
        'nieuw'      => 'NW',
        'verlengde'  => 'VERL',
    ];

    /**
     * Splits one street line of a Dutch (NL) or Belgian (BE) address. Use it for the
     * countries in which MyParcel needs the house number in its own field. Returns
     * null when the line has no house number or does not match, and for every other
     * country.
     *
     * @return null|array{street: string, number: string, numberSuffix: ?string, boxNumber: ?string}
     */
    public function split(string $street, string $cc): ?array
    {
        $street = trim((string) preg_replace('/\r\n|\n|\r/', ' ', $street));

        return match ($cc) {
            'NL'    => $this->match(self::REGEX_NL, $this->abbreviateNumberSuffix($street)),
            'BE'    => $this->matchBelgian($street),
            default => null,
        };
    }

    private function abbreviateNumberSuffix(string $street): string
    {
        foreach (self::NUMBER_SUFFIX_ABBREVIATIONS as $word => $abbreviation) {
            $street = (string) preg_replace('/(\d.*-?)\s' . $word . '/', '$1' . $abbreviation, $street);
        }

        return $street;
    }

    /**
     * Writes every box separator as "bus" first, the same as the SDK, so the second
     * match finds the box number.
     *
     * @return null|array{street: string, number: string, numberSuffix: ?string, boxNumber: ?string}
     */
    private function matchBelgian(string $street): ?array
    {
        if (1 !== preg_match(self::REGEX_BE, $street, $matches)) {
            return null;
        }

        $separator = $matches['box_separator'] ?? '';

        if (in_array($separator, self::BOX_SEPARATORS, true)) {
            $separator = 'bus';
        }

        $normalized = implode(' ', array_filter([
            $matches['street'] ?? $street,
            $matches['number'] ?? '',
            $separator,
            $matches['box_number'] ?? '',
            $matches['number_suffix'] ?? '',
        ]));

        foreach (self::BOX_SEPARATORS_BEFORE_DIGIT as $boxSeparator) {
            $normalized = (string) preg_replace('#' . $boxSeparator . '([0-9])#', 'bus $1', $normalized);
        }

        return $this->match(self::REGEX_BE, $normalized);
    }

    /**
     * Accepts a match only when it covers the whole line and has a house number.
     *
     * @return null|array{street: string, number: string, numberSuffix: ?string, boxNumber: ?string}
     */
    private function match(string $regex, string $street): ?array
    {
        if (1 !== preg_match($regex, $street, $matches) || $matches[0] !== $street) {
            return null;
        }

        $number = (int) ($matches['number'] ?? 0);

        if (0 === $number) {
            return null;
        }

        return [
            'street'       => $matches['street'] ?? $street,
            'number'       => (string) $number,
            'numberSuffix' => ($matches['number_suffix'] ?? '') ?: null,
            'boxNumber'    => ($matches['box_number'] ?? '') ?: null,
        ];
    }
}
