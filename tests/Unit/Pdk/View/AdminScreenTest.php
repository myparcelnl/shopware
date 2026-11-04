<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\View\AdminScreen;

it('is requestable for every screen except the child product', function (AdminScreen $screen, bool $requestable) {
    expect($screen->isRequestable())->toBe($requestable);
})->with([
    [AdminScreen::PluginSettings, true],
    [AdminScreen::OrderList, true],
    [AdminScreen::Order, true],
    [AdminScreen::Product, true],
    [AdminScreen::ChildProduct, false],
]);
