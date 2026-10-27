<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\View;

/**
 * What the admin app asked the view route to render: the view and the IDs it
 * renders for.
 */
final class AdminViewRequest
{
    public const MAX_ORDER_IDS = 500;

    /**
     * Shopware's own UUID pattern (Uuid::VALID_PATTERN): 32 lowercase hex
     * characters. Copied here because the unit tests have no shopware/core.
     */
    private const UUID_PATTERN = '/^[0-9a-f]{32}$/';

    /**
     * @param list<string> $ids the order IDs or the product ID, empty for views without context
     */
    public function __construct(public readonly AdminView $view, public readonly array $ids)
    {
    }

    /**
     * Reads a request from the query string. Null when the view is unknown or
     * not requestable, or when its IDs are missing or no valid UUID: the
     * controller answers 400.
     *
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): ?self
    {
        $value = $query['view'] ?? null;
        $view  = is_string($value) ? AdminView::tryFrom($value) : null;

        if (null === $view || !$view->isRequestable()) {
            return null;
        }

        $ids = match ($view) {
            AdminView::OrderList => self::readIds($query['orderIds'] ?? null),
            AdminView::Order     => self::readIds([$query['orderId'] ?? null], 1),
            AdminView::Product   => self::readIds([$query['productId'] ?? null], 1),
            default              => [],
        };

        return null === $ids ? null : new self($view, $ids);
    }

    /**
     * @return null|list<string> null when the list is empty, too long or holds a non-UUID
     */
    private static function readIds(mixed $value, int $max = self::MAX_ORDER_IDS): ?array
    {
        if (!is_array($value) || [] === $value) {
            return null;
        }

        foreach ($value as $id) {
            if (!is_string($id) || 1 !== preg_match(self::UUID_PATTERN, $id)) {
                return null;
            }
        }

        $ids = array_values(array_unique($value));

        return count($ids) > $max ? null : $ids;
    }
}
