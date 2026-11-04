<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\DeliveryOptionsView;
use MyParcel\Shopware\Checkout\Subscriber\CheckoutConfirmPageSubscriber;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\FixedDeliveryOptionsRenderer;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use Psr\Log\LogLevel;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

function confirmPageEvent(): CheckoutConfirmPageLoadedEvent
{
    $page = new CheckoutConfirmPage();
    $page->setCart(Checkout::cart());

    return new CheckoutConfirmPageLoadedEvent($page, Checkout::context(), new Request());
}

it('listens to the confirm page', function () {
    expect(CheckoutConfirmPageSubscriber::getSubscribedEvents())
        ->toBe([CheckoutConfirmPageLoadedEvent::class => 'onConfirmPageLoaded']);
});

it('adds the delivery options of the page cart to the page', function () {
    $view     = new DeliveryOptionsView('<div id="mypa-delivery-options-wrapper"></div>', 'https://cdn.test/myparcel.js', 'https://cdn.test/style.css');
    $renderer = new FixedDeliveryOptionsRenderer($view);
    $event    = confirmPageEvent();

    (new CheckoutConfirmPageSubscriber($renderer, new RecordingLogger()))->onConfirmPageLoaded($event);

    expect($event->getPage()->getExtension(DeliveryOptionsView::EXTENSION_NAME))->toBe($view)
        ->and($renderer->rendered[0][0])->toBe($event->getPage()->getCart())
        ->and($renderer->rendered[0][1])->toBe($event->getSalesChannelContext());
});

it('adds nothing when the pdk renders nothing', function () {
    $event = confirmPageEvent();

    (new CheckoutConfirmPageSubscriber(new FixedDeliveryOptionsRenderer(), new RecordingLogger()))->onConfirmPageLoaded($event);

    expect($event->getPage()->hasExtension(DeliveryOptionsView::EXTENSION_NAME))->toBeFalse();
});

it('logs a failure and keeps the checkout working without delivery options', function () {
    $logger = new RecordingLogger();
    $event  = confirmPageEvent();

    (new CheckoutConfirmPageSubscriber(new FixedDeliveryOptionsRenderer(null, new RuntimeException('API down')), $logger))
        ->onConfirmPageLoaded($event);

    expect($event->getPage()->hasExtension(DeliveryOptionsView::EXTENSION_NAME))->toBeFalse()
        ->and($logger->records[0]['level'])->toBe(LogLevel::ERROR)
        ->and($logger->records[0]['context']['exception']->getMessage())->toBe('API down');
});
