<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout;

use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcelNL\Pdk\App\Cart\Contract\PdkCartRepositoryInterface;
use MyParcelNL\Pdk\Facade\Frontend;
use MyParcelNL\Pdk\Facade\Pdk;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Renders the delivery options with the PDK for the confirm page.
 *
 * @TODO INT-2151: cover this class with an integration test in a Shopware installation.
 */
final class DeliveryOptionsRenderer implements DeliveryOptionsRendererInterface
{
    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly SalesChannelContextHolder $contextHolder
    ) {
    }

    /**
     * Puts the context in the holder first: the PDK cart repository and the tax
     * service read it there.
     */
    public function render(Cart $cart, SalesChannelContext $context): ?DeliveryOptionsView
    {
        $this->contextHolder->set($context);
        $this->pdkInitializer->boot();

        /** @var PdkCartRepositoryInterface $cartRepository */
        $cartRepository = Pdk::get(PdkCartRepositoryInterface::class);

        $html = Frontend::renderDeliveryOptions($cartRepository->get($cart));

        if ('' === $html) {
            return null;
        }

        return new DeliveryOptionsView($html, Pdk::get('deliveryOptionsCdnUrlJs'), Pdk::get('deliveryOptionsCdnUrlCss'));
    }
}
