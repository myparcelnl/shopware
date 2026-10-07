<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Command;

use MyParcelNL\Pdk\Account\Model\Account;
use MyParcelNL\Pdk\Carrier\Model\Carrier;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prints the parts of an account that show the key works: which platform it is
 * on, which shop it belongs to and which carriers the contract offers.
 */
final class AccountOutput
{
    public static function write(SymfonyStyle $io, Account $account): void
    {
        $shop = $account->shops->first();

        $io->definitionList(
            ['Account ID' => (string) $account->id],
            ['Platform ID' => (string) $account->platformId],
            ['Shop' => $shop ? sprintf('%s (%d)', $shop->name, $shop->id) : '-'],
            ['Default carrier' => $shop->defaultCarrier ?? '-']
        );

        $carriers = $shop
            ? array_map(static fn (Carrier $carrier): string => $carrier->carrier, $shop->carriers->all())
            : [];

        $io->section('Carriers');

        if ([] === $carriers) {
            $io->text('None');

            return;
        }

        $io->listing($carriers);
    }
}
