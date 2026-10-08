<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Frontend\AdminViewRendererInterface;
use MyParcel\Shopware\Pdk\View\AdminView;
use Throwable;

final class FixedAdminViewRenderer implements AdminViewRendererInterface
{
    /**
     * @var list<AdminView>
     */
    public array $rendered = [];

    public function __construct(private readonly string $html = '<div></div>', private readonly ?Throwable $error = null)
    {
    }

    public function render(AdminView $view): string
    {
        if (null !== $this->error) {
            throw $this->error;
        }

        $this->rendered[] = $view;

        return $this->html;
    }
}
