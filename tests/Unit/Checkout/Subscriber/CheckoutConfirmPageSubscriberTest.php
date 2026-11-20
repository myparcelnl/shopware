<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\DeliveryOptionsView;
use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Checkout\Subscriber\CheckoutConfirmPageSubscriber;
use MyParcel\Shopware\Pdk\Frontend\FrontendRenderService;
use MyParcel\Shopware\Pdk\Frontend\ViewService;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\PdkServices;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Cart\Contract\PdkCartRepositoryInterface;
use MyParcelNL\Pdk\Base\FileSystemInterface;
use MyParcelNL\Pdk\Context\Contract\ContextServiceInterface;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Frontend\Contract\FrontendRenderServiceInterface;
use MyParcelNL\Pdk\Settings\Model\CheckoutSettings;
use Psr\Log\LogLevel;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function DI\factory;
use function MyParcelNL\Pdk\Tests\factory as pdkFactory;

/**
 * Boots the mock PDK with our cart repository and our render service on the
 * confirm page, as the production container binds them.
 */
function bootConfirmPage(SalesChannelContextHolder $holder, ?Throwable $renderFailure = null): void
{
    TestPdk::boot([
        PdkCartRepositoryInterface::class     => PdkServices::cartRepository($holder),
        FrontendRenderServiceInterface::class => factory(static function () use ($renderFailure) {
            if (null !== $renderFailure) {
                throw $renderFailure;
            }

            $requestStack = new RequestStack();
            $requestStack->push(new Request([], [], ['_route' => ViewService::CHECKOUT_ROUTE]));

            return new FrontendRenderService(Pdk::get(ContextServiceInterface::class), new ViewService($requestStack), Pdk::get(FileSystemInterface::class));
        }),
    ]);
}

/**
 * @param  array<string, mixed> $contextValues see Checkout::context()
 */
function confirmPageEvent(array $contextValues = []): CheckoutConfirmPageLoadedEvent
{
    $page = new CheckoutConfirmPage();
    $page->setCart(Checkout::cart());

    return new CheckoutConfirmPageLoadedEvent($page, Checkout::context($contextValues), new Request());
}

function confirmPageSubscriber(SalesChannelContextHolder $holder, ?RecordingLogger $logger = null): CheckoutConfirmPageSubscriber
{
    $logger ??= new RecordingLogger();

    return new CheckoutConfirmPageSubscriber(PdkServices::initializer($logger, $holder), $holder, $logger);
}

it('listens to the confirm page', function () {
    expect(CheckoutConfirmPageSubscriber::getSubscribedEvents())
        ->toBe([CheckoutConfirmPageLoadedEvent::class => 'onConfirmPageLoaded']);
});

it('adds the delivery options of the page cart to the page', function () {
    $holder = new SalesChannelContextHolder();
    bootConfirmPage($holder);
    $event = confirmPageEvent();

    confirmPageSubscriber($holder)->onConfirmPageLoaded($event);

    /** @var DeliveryOptionsView $view */
    $view = $event->getPage()->getExtension(DeliveryOptionsView::EXTENSION_NAME);

    expect($view)->toBeInstanceOf(DeliveryOptionsView::class)
        ->and($view->html)->toContain('id="mypa-delivery-options-wrapper"')
        ->and($view->scriptUrl)->toBe(Pdk::get('deliveryOptionsCdnUrlJs'))
        ->and($view->styleUrl)->toBe(Pdk::get('deliveryOptionsCdnUrlCss'))
        ->and($view->address2)->toBe('')
        ->and($holder->get())->toBe($event->getSalesChannelContext());
});

dataset('address2', [
    'number in line 1'            => [['street' => 'Teststraat', 'additionalAddressLine1' => ' 12 '], '12'],
    'digit in the street name'    => [['street' => '2e Hugo de Grootstraat', 'additionalAddressLine1' => '12'], '12'],
    'number in the street'        => [['street' => 'Teststraat 12', 'additionalAddressLine1' => '2e etage'], ''],
    'apartment in line 1'         => [['street' => 'Teststraat', 'additionalAddressLine1' => 'Appartement 3'], ''],
    'no separate address fields'  => [['street' => 'Hauptstraße', 'additionalAddressLine1' => '5', 'countryIso' => 'DE'], ''],
    'only a country'              => [['withAddress' => false], ''],
]);

it('gives the widget line 1 only when it holds the house number of the order', function (array $contextValues, string $address2) {
    $holder = new SalesChannelContextHolder();
    bootConfirmPage($holder);
    $event = confirmPageEvent($contextValues);

    confirmPageSubscriber($holder)->onConfirmPageLoaded($event);

    /** @var DeliveryOptionsView $view */
    $view = $event->getPage()->getExtension(DeliveryOptionsView::EXTENSION_NAME);

    expect($view->address2)->toBe($address2);
})->with('address2');

it('adds nothing when the pdk renders nothing', function () {
    $holder = new SalesChannelContextHolder();
    bootConfirmPage($holder);
    pdkFactory(CheckoutSettings::class)->withEnableDeliveryOptions(false)->store();
    $event = confirmPageEvent();

    confirmPageSubscriber($holder)->onConfirmPageLoaded($event);

    expect($event->getPage()->hasExtension(DeliveryOptionsView::EXTENSION_NAME))->toBeFalse();
});

it('logs a failure and keeps the checkout working without delivery options', function () {
    $holder = new SalesChannelContextHolder();
    bootConfirmPage($holder, new RuntimeException('API down'));
    $logger = new RecordingLogger();
    $event  = confirmPageEvent();

    confirmPageSubscriber($holder, $logger)->onConfirmPageLoaded($event);

    expect($event->getPage()->hasExtension(DeliveryOptionsView::EXTENSION_NAME))->toBeFalse()
        ->and($logger->records[0]['level'])->toBe(LogLevel::ERROR)
        ->and($logger->records[0]['context']['exception']->getMessage())->toContain('API down');
});
