<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Language;

use Doctrine\DBAL\Connection;

/**
 * A plain query, for the same reason as ShopwareLocaleResolver: a repository
 * search with the admin context needs language:read.
 */
final class ShopwareLanguageIdResolver implements LanguageIdResolverInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getLanguageId(string $locale): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT LOWER(HEX(language.id)) FROM language INNER JOIN locale ON locale.id = language.locale_id WHERE locale.code = :code LIMIT 1',
            ['code' => $locale]
        );

        return is_string($id) ? $id : null;
    }
}
