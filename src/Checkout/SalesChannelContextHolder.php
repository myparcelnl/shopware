<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Keeps the sales channel context of the current storefront request for the
 * PDK services.
 *
 * The PDK asks for the cart without saying whose cart (FetchCheckoutContextAction
 * calls PdkCartRepositoryInterface::get(null)), and PdkHttpBridge does not pass
 * the Shopware request attributes to the PDK. The storefront entry points put the
 * context here before they call the PDK. The kernel.reset tag clears it between
 * requests on a long-running worker.
 */
final class SalesChannelContextHolder implements ResetInterface
{
    private ?SalesChannelContext $context = null;

    /**
     * Stores the context of the current request. Call it before a PDK call that
     * reads the cart.
     */
    public function set(SalesChannelContext $context): void
    {
        $this->context = $context;
    }

    /**
     * Returns the context of the current request.
     *
     * @throws \LogicException when no storefront entry point stored a context
     */
    public function get(): SalesChannelContext
    {
        return $this->context
            ?? throw new \LogicException('MyParcel: no sales channel context is set for this request');
    }

    /**
     * Tells if a storefront entry point stored a context.
     */
    public function has(): bool
    {
        return null !== $this->context;
    }

    public function reset(): void
    {
        $this->context = null;
    }
}
