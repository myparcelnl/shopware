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
