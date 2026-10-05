<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Webhook\Service;

use MyParcel\Shopware\Pdk\Routing\RouteName;
use MyParcel\Shopware\Pdk\Routing\UrlResolverInterface;
use MyParcelNL\Pdk\App\Webhook\Service\AbstractPdkWebhookService;

/**
 * The webhook route has an empty hash as default, so the router generates the
 * URL without one. The PDK appends "/<hash>" in createUrl().
 */
final class PdkWebhookService extends AbstractPdkWebhookService
{
    public function __construct(private readonly UrlResolverInterface $urlResolver)
    {
    }

    public function getBaseUrl(): string
    {
        return $this->urlResolver->getAbsoluteUrl(RouteName::WEBHOOK);
    }
}
