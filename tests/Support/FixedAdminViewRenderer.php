<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use MyParcel\Shopware\Pdk\Frontend\AdminViewRendererInterface;
use MyParcel\Shopware\Pdk\Frontend\AdminViewResult;
use MyParcel\Shopware\Pdk\View\AdminViewRequest;
use Throwable;

final class FixedAdminViewRenderer implements AdminViewRendererInterface
{
    /**
     * @var list<AdminViewRequest>
     */
    public array $rendered = [];

    private readonly AdminViewResult $result;

    public function __construct(AdminViewResult|string $result = '<div></div>', private readonly ?Throwable $error = null)
    {
        $this->result = is_string($result) ? new AdminViewResult($result) : $result;
    }

    public function render(AdminViewRequest $request): AdminViewResult
    {
        if (null !== $this->error) {
            throw $this->error;
        }

        $this->rendered[] = $request;

        return $this->result;
    }
}
