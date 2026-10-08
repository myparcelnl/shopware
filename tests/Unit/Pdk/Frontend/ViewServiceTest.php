<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Frontend\ViewService;
use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Tests\Support\FixedViewResolver;
use MyParcelNL\Pdk\Frontend\Service\AbstractViewService;

it('is a pdk view service', function () {
    expect(new ViewService(new FixedViewResolver(null)))->toBeInstanceOf(AbstractViewService::class);
});

it('is the plugin settings page for the plugin settings view', function () {
    $service = new ViewService(new FixedViewResolver(AdminView::PluginSettings));

    expect($service->isPluginSettingsPage())->toBeTrue()
        ->and($service->isAnyPdkPage())->toBeTrue()
        ->and($service->hasNotifications())->toBeTrue()
        ->and($service->hasModals())->toBeFalse();
});

it('is no pdk page without a view', function () {
    $service = new ViewService(new FixedViewResolver(null));

    expect($service->isPluginSettingsPage())->toBeFalse()
        ->and($service->isAnyPdkPage())->toBeFalse();
});

it('has no other pages yet', function (string $method) {
    expect((new ViewService(new FixedViewResolver(AdminView::PluginSettings)))->{$method}())->toBeFalse();
})->with(['isCheckoutPage', 'isChildProductPage', 'isOrderListPage', 'isOrderPage', 'isProductPage']);
