<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

/**
 * What the view route renders for one request.
 */
final class AdminViewResult
{
    /**
     * @param string                                      $html        the shell, or the shell and the view
     * @param array<string, string>                       $items       order ID => order list item HTML
     * @param list<array{action: string, label: string}>  $bulkActions the bulk actions of the order list
     */
    public function __construct(
        public readonly string $html,
        public readonly array $items = [],
        public readonly array $bulkActions = []
    ) {
    }
}
