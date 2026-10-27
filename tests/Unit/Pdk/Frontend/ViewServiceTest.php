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

it('maps each view to its pdk page', function (AdminView $view, string $method) {
    expect((new ViewService(new FixedViewResolver($view)))->{$method}())->toBeTrue();
})->with([
    [AdminView::OrderList, 'isOrderListPage'],
    [AdminView::Order, 'isOrderPage'],
    [AdminView::Product, 'isProductPage'],
    [AdminView::ChildProduct, 'isChildProductPage'],
]);

it('is one page at a time', function () {
    $service = new ViewService(new FixedViewResolver(AdminView::Product));

    expect($service->isChildProductPage())->toBeFalse()
        ->and($service->isOrderPage())->toBeFalse()
        ->and($service->isPluginSettingsPage())->toBeFalse();
});

it('has modals on the order pages only', function (AdminView $view, bool $modals) {
    expect((new ViewService(new FixedViewResolver($view)))->hasModals())->toBe($modals);
})->with([
    [AdminView::OrderList, true], [AdminView::Order, true],
    [AdminView::Product, false], [AdminView::PluginSettings, false],
]);

it('is never the checkout in the admin', function () {
    expect((new ViewService(new FixedViewResolver(AdminView::OrderList)))->isCheckoutPage())->toBeFalse();
});
