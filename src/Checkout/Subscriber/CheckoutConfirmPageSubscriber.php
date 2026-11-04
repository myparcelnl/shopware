<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Checkout\Subscriber;

use MyParcel\Shopware\Checkout\DeliveryOptionsRendererInterface;
use MyParcel\Shopware\Checkout\DeliveryOptionsView;
use Psr\Log\LoggerInterface;
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
        private readonly DeliveryOptionsRendererInterface $renderer,
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
            $view = $this->renderer->render($page->getCart(), $event->getSalesChannelContext());
        } catch (Throwable $exception) {
            $this->logger->error('MyParcel: could not render the delivery options on the confirm page', ['exception' => $exception]);

            return;
        }

        if (null !== $view) {
            $page->addExtension(DeliveryOptionsView::EXTENSION_NAME, $view);
        }
    }
}
