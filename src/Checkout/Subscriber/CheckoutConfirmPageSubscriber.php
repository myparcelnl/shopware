<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout\Subscriber;

use MyParcel\Shopware\Checkout\DeliveryOptionsView;
use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\Order\Mapper\AddressMapper;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcelNL\Pdk\App\Cart\Contract\PdkCartRepositoryInterface;
use MyParcelNL\Pdk\Facade\Frontend;
use MyParcelNL\Pdk\Facade\Pdk;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

/**
 * Adds the delivery options to the confirm page.
 *
 * The page only reads: it does not write or recalculate the cart, so the cart
 * total and the order hash on the page stay valid. When rendering fails (for
 * example, the MyParcel API cannot be reached) the page shows no delivery
 * options and the consumer can still order.
 */
final class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly SalesChannelContextHolder $contextHolder,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckoutConfirmPageLoadedEvent::class => 'onConfirmPageLoaded'];
    }

    /**
     * Renders the delivery options and adds them to the page as the extension
     * "myparcel".
     */
    public function onConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $page = $event->getPage();

        try {
            $view = $this->render($page->getCart(), $event->getSalesChannelContext());
        } catch (Throwable $exception) {
            $this->logger->error('MyParcel: could not render the delivery options on the confirm page', ['exception' => $exception]);

            return;
        }

        if (null !== $view) {
            $page->addExtension(DeliveryOptionsView::EXTENSION_NAME, $view);
        }
    }

    /**
     * Null when the PDK renders nothing: delivery options off, or a cart
     * without delivery options.
     */
    private function render(Cart $cart, SalesChannelContext $context): ?DeliveryOptionsView
    {
        // The PDK cart repository and the tax service read the context in the holder.
        $this->contextHolder->set($context);
        $this->pdkInitializer->boot();

        /** @var PdkCartRepositoryInterface $cartRepository */
        $cartRepository = Pdk::get(PdkCartRepositoryInterface::class);

        $html = Frontend::renderDeliveryOptions($cartRepository->get($cart));

        if ('' === $html) {
            return null;
        }

        return new DeliveryOptionsView(
            $html,
            Pdk::get('deliveryOptionsCdnUrlJs'),
            Pdk::get('deliveryOptionsCdnUrlCss'),
            self::address2($context)
        );
    }

    /**
     * additionalAddressLine1 when it holds the house number, as the order gets it
     * from AddressMapper. Otherwise empty.
     */
    private static function address2(SalesChannelContext $context): string
    {
        $address = $context->getShippingLocation()->getAddress();
        $line1   = (string) $address?->getAdditionalAddressLine1();

        if (null === $address || '' === trim($line1)) {
            return '';
        }

        /** @var AddressMapper $addressMapper */
        $addressMapper = Pdk::get(AddressMapper::class);
        $cc            = (string) $context->getShippingLocation()->getCountry()->getIso();

        return $addressMapper->houseNumberInLine1($address->getStreet(), $line1, $cc) ? trim($line1) : '';
    }
}
