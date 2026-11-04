<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order\Mapper;

/**
 * The contact details for a PDK address, and whether the street split failed.
 * The caller logs a failed split: the mapper has no order id to log.
 */
final class AddressMapResult
{
    /**
     * @param  array<string, null|string> $contactDetails
     */
    public function __construct(
        public readonly array $contactDetails,
        public readonly bool $splitFailed
    ) {
    }
}
