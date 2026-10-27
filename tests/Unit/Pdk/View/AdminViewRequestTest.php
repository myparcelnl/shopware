<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Pdk\View\AdminViewRequest;

const REQUEST_UUID_A = '0190a1b2c3d4e5f60718293a4b5c6d01';
const REQUEST_UUID_B = '0190a1b2c3d4e5f60718293a4b5c6d02';

it('reads the view and its ids from the query', function (array $query, ?AdminView $view, ?array $ids) {
    $request = AdminViewRequest::fromQuery($query);

    if (null === $view) {
        expect($request)->toBeNull();

        return;
    }

    expect($request->view)->toBe($view)
        ->and($request->ids)->toBe($ids);
})->with([
    'plugin settings'          => [['view' => 'pluginSettings'], AdminView::PluginSettings, []],
    'order list'               => [['view' => 'orderList', 'orderIds' => [REQUEST_UUID_A, REQUEST_UUID_B]], AdminView::OrderList, [REQUEST_UUID_A, REQUEST_UUID_B]],
    'order list without ids'   => [['view' => 'orderList'], null, null],
    'order list with no ids'   => [['view' => 'orderList', 'orderIds' => []], null, null],
    'order list with a bad id' => [['view' => 'orderList', 'orderIds' => [REQUEST_UUID_A, 'nope']], null, null],
    'order list with a string' => [['view' => 'orderList', 'orderIds' => REQUEST_UUID_A], null, null],
    '500 order ids'            => [['view' => 'orderList', 'orderIds' => array_map(fn (int $i) => sprintf('%032x', $i), range(1, 500))], AdminView::OrderList, array_map(fn (int $i) => sprintf('%032x', $i), range(1, 500))],
    '501 order ids'            => [['view' => 'orderList', 'orderIds' => array_map(fn (int $i) => sprintf('%032x', $i), range(1, 501))], null, null],
    'duplicates are removed'   => [['view' => 'orderList', 'orderIds' => [REQUEST_UUID_A, REQUEST_UUID_A]], AdminView::OrderList, [REQUEST_UUID_A]],
    'order'                    => [['view' => 'order', 'orderId' => REQUEST_UUID_A], AdminView::Order, [REQUEST_UUID_A]],
    'order without id'         => [['view' => 'order'], null, null],
    'order with a bad id'      => [['view' => 'order', 'orderId' => 'nope'], null, null],
    'order with an array'      => [['view' => 'order', 'orderId' => [REQUEST_UUID_A]], null, null],
    'uppercase id'             => [['view' => 'order', 'orderId' => strtoupper(REQUEST_UUID_A)], null, null],
    'product'                  => [['view' => 'product', 'productId' => REQUEST_UUID_A], AdminView::Product, [REQUEST_UUID_A]],
    'product without id'       => [['view' => 'product'], null, null],
    'product with a bad id'    => [['view' => 'product', 'productId' => 'nope'], null, null],
    'child product'            => [['view' => 'childProduct'], null, null],
    'child product with id'    => [['view' => 'childProduct', 'productId' => REQUEST_UUID_A], null, null],
    'unknown'                  => [['view' => 'unknownView'], null, null],
    'view as array'            => [['view' => ['order']], null, null],
    'no view'                  => [[], null, null],
]);
