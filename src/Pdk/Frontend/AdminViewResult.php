<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Frontend;

/**
 * What the view route renders for one request.
 */
final class AdminViewResult
{
    /**
     * @param string                                      $html          the shell, or the shell and the view
     * @param array<string, string>                       $items         order ID => order list item HTML
     * @param list<array{action: string, label: string}>  $bulkActions   the bulk actions of the order list
     * @param list<array<string, mixed>>                  $notifications what the PDK reported while rendering
     */
    public function __construct(
        public readonly string $html,
        public readonly array $items = [],
        public readonly array $bulkActions = [],
        public readonly array $notifications = []
    ) {
    }

    /**
     * Returns a copy that carries these notifications. The renderer collects
     * them after the render, because the PDK adds them while it renders.
     *
     * @param list<array<string, mixed>> $notifications
     */
    public function withNotifications(array $notifications): self
    {
        return new self($this->html, $this->items, $this->bulkActions, $notifications);
    }
}
