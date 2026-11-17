<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\View;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * What the admin app asked the view route to render: the screen and the IDs it
 * renders for.
 */
final class AdminViewRequest
{
    /**
     * Keeps one request from rendering an unlimited number of orders. 100 is the
     * biggest step of sw-pagination in Shopware 6.7. A larger limit in the URL of
     * the order list gets a 400, and the order list shows its load error.
     */
    public const MAX_ORDER_IDS = 100;

    /**
     * @param list<string> $ids the order IDs or the product ID, empty for screens without context
     */
    public function __construct(public readonly AdminScreen $screen, public readonly array $ids)
    {
    }

    /**
     * Read a request from the query string. Null when the screen is unknown or
     * not requestable, or when its IDs are missing or no valid UUID: the
     * controller answers 400.
     *
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): ?self
    {
        $value  = $query['view'] ?? null;
        $screen = is_string($value) ? AdminScreen::tryFrom($value) : null;

        if (null === $screen || !$screen->isRequestable()) {
            return null;
        }

        $ids = match ($screen) {
            AdminScreen::OrderList => self::readIds($query['orderIds'] ?? null, self::MAX_ORDER_IDS),
            AdminScreen::Order     => self::readIds([$query['orderId'] ?? null], 1),
            AdminScreen::Product   => self::readIds([$query['productId'] ?? null], 1),
            default                => [],
        };

        return null === $ids ? null : new self($screen, $ids);
    }

    /**
     * @return null|list<string> null when the list is empty, too long or holds a non-UUID
     */
    private static function readIds(mixed $value, int $max): ?array
    {
        if (!is_array($value) || [] === $value) {
            return null;
        }

        foreach ($value as $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                return null;
            }
        }

        $ids = array_values(array_unique($value));

        return count($ids) > $max ? null : $ids;
    }
}
