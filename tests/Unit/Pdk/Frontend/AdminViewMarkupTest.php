<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Frontend\AdminViewMarkup;
use MyParcel\Shopware\Pdk\Frontend\EmptyAdminViewException;
use MyParcel\Shopware\Pdk\View\AdminScreen;

it('joins the boot element, notifications, modals and the view', function () {
    expect(AdminViewMarkup::compose(AdminScreen::PluginSettings, '<span>', '<n>', '', '<div>'))
        ->toBe('<span><n><div>');
});

it('fails when the boot element is empty, because the app cannot start', function () {
    expect(fn () => AdminViewMarkup::compose(AdminScreen::PluginSettings, '', '<n>', '', '<div>'))
        ->toThrow(EmptyAdminViewException::class, 'pluginSettings');
});

it('fails when the view is empty, so the admin shows an error instead of an empty card', function () {
    expect(fn () => AdminViewMarkup::compose(AdminScreen::PluginSettings, '<span>', '<n>', '', ''))
        ->toThrow(EmptyAdminViewException::class, 'pluginSettings');
});
