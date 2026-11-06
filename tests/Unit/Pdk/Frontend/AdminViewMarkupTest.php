<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Frontend\AdminViewMarkup;
use MyParcel\Shopware\Pdk\Frontend\EmptyAdminViewException;
use MyParcel\Shopware\Pdk\View\AdminScreen;

it('joins the bootstrap container, notifications, modals and the view', function () {
    expect(AdminViewMarkup::compose(AdminScreen::PluginSettings, '<span>', '<n>', '', '<div>'))
        ->toBe('<span><n><div>');
});

it('fails when the bootstrap container is empty, because the app cannot start', function () {
    expect(fn () => AdminViewMarkup::compose(AdminScreen::PluginSettings, '', '<n>', '', '<div>'))
        ->toThrow(EmptyAdminViewException::class, 'pluginSettings');
});

it('fails when the view is empty, so the admin shows an error instead of an empty card', function () {
    expect(fn () => AdminViewMarkup::compose(AdminScreen::PluginSettings, '<span>', '<n>', '', ''))
        ->toThrow(EmptyAdminViewException::class, 'pluginSettings');
});

it('joins the bootstrap container, notifications and modals into a shell', function () {
    expect(AdminViewMarkup::composeShell(AdminScreen::OrderList, '<span>', '<n>', '<m>'))->toBe('<span><n><m>');
});

it('fails to build a shell without a bootstrap container', function () {
    expect(fn () => AdminViewMarkup::composeShell(AdminScreen::OrderList, '', '<n>', '<m>'))
        ->toThrow(EmptyAdminViewException::class, 'orderList');
});
