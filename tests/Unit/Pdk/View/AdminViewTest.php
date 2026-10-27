<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\View\AdminView;

it('is requestable for every view except the child product', function (AdminView $view, bool $requestable) {
    expect($view->isRequestable())->toBe($requestable);
})->with([
    [AdminView::PluginSettings, true],
    [AdminView::OrderList, true],
    [AdminView::Order, true],
    [AdminView::Product, true],
    [AdminView::ChildProduct, false],
]);
