<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Frontend\AdminViewRendererInterface;
use MyParcel\Shopware\Pdk\View\AdminScreen;
use Throwable;

final class FixedAdminViewRenderer implements AdminViewRendererInterface
{
    /**
     * @var list<AdminScreen>
     */
    public array $rendered = [];

    public function __construct(private readonly string $html = '<div></div>', private readonly ?Throwable $error = null)
    {
    }

    public function render(AdminScreen $screen): string
    {
        if (null !== $this->error) {
            throw $this->error;
        }

        $this->rendered[] = $screen;

        return $this->html;
    }
}
