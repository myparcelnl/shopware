<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Frontend\ViewService;
use MyParcel\Shopware\Pdk\View\AdminScreen;
use MyParcelNL\Pdk\Frontend\Service\AbstractViewService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

function viewService(?AdminScreen $screen): ViewService
{
    $requestStack = new RequestStack();
    $request      = new Request();

    if (null !== $screen) {
        $request->attributes->set(AdminScreen::REQUEST_ATTRIBUTE, $screen);
    }

    $requestStack->push($request);

    return new ViewService($requestStack);
}

it('is a pdk view service', function () {
    expect(viewService(null))->toBeInstanceOf(AbstractViewService::class);
});

it('is the plugin settings page for the plugin settings view', function () {
    $service = viewService(AdminScreen::PluginSettings);

    expect($service->isPluginSettingsPage())->toBeTrue()
        ->and($service->isAnyPdkPage())->toBeTrue()
        ->and($service->hasNotifications())->toBeTrue()
        ->and($service->hasModals())->toBeFalse();
});

it('is no pdk page without a view', function () {
    $service = viewService(null);

    expect($service->isPluginSettingsPage())->toBeFalse()
        ->and($service->isAnyPdkPage())->toBeFalse();
});

it('is no pdk page outside a request', function () {
    expect((new ViewService(new RequestStack()))->isPluginSettingsPage())->toBeFalse();
});

it('has no other pages yet', function (string $method) {
    expect(viewService(AdminScreen::PluginSettings)->{$method}())->toBeFalse();
})->with(['isCheckoutPage', 'isChildProductPage', 'isOrderListPage', 'isOrderPage', 'isProductPage']);

it('is the checkout page on the confirm page route', function () {
    $requestStack = new RequestStack();
    $requestStack->push(new Request([], [], ['_route' => ViewService::CHECKOUT_ROUTE]));
    $service = new ViewService($requestStack);

    expect($service->isCheckoutPage())->toBeTrue()
        // The checkout is no admin page, so the admin notifications stay off.
        ->and($service->isAnyPdkPage())->toBeFalse();
});

it('is no checkout page on other storefront routes', function (string $route) {
    $requestStack = new RequestStack();
    $requestStack->push(new Request([], [], ['_route' => $route]));

    expect((new ViewService($requestStack))->isCheckoutPage())->toBeFalse();
})->with(['frontend.checkout.cart.page', 'frontend.checkout.finish.page', 'frontend.myparcel.pdk']);
