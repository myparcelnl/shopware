<?php

declare(strict_types=1);

use MyParcel\Shopware\Admin\AdminAssets;
use MyParcel\Shopware\Controller\AdminViewController;
use MyParcel\Shopware\Pdk\Frontend\AdminViewNotFoundException;
use MyParcel\Shopware\Pdk\Frontend\AdminViewResult;
use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Pdk\View\AdminViewRequest;
use MyParcel\Shopware\Tests\Support\FixedAdminViewRenderer;
use MyParcel\Shopware\Tests\Support\HttpBridgeFactory;
use MyParcel\Shopware\Tests\Support\PrefixAssetUrlResolver;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use Symfony\Component\HttpFoundation\Request;

const CONTROLLER_ORDER_ID = '0190a1b2c3d4e5f60718293a4b5c6d01';

function viewController(FixedAdminViewRenderer $renderer, RecordingLogger $logger = new RecordingLogger()): AdminViewController
{
    return new AdminViewController(
        HttpBridgeFactory::create($logger),
        $renderer,
        new AdminAssets(new PrefixAssetUrlResolver())
    );
}

it('returns the rendered view with its assets', function () {
    $renderer = new FixedAdminViewRenderer('<span id="myparcel-pdk-boot"></span>');
    $request  = Request::create('/api/_action/myparcel/view', 'GET', ['view' => 'pluginSettings']);

    $response = viewController($renderer)->view($request);

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode((string) $response->getContent(), true))->toBe([
            'html'        => '<span id="myparcel-pdk-boot"></span>',
            'items'       => [],
            'bulkActions' => [],
            'scripts'     => ['https://shop.test/bundles/myparcelshopware/pdk/admin.iife.js?1'],
            'styles'      => ['https://shop.test/bundles/myparcelshopware/pdk/admin.css?1'],
        ])
        ->and($renderer->rendered)->toEqual([new AdminViewRequest(AdminView::PluginSettings, [])])
        ->and($request->attributes->get(AdminView::REQUEST_ATTRIBUTE))->toBe(AdminView::PluginSettings);
});

it('rejects an unknown view without rendering', function (array $query) {
    $renderer = new FixedAdminViewRenderer();

    $response = viewController($renderer)->view(Request::create('/api/_action/myparcel/view', 'GET', $query));

    expect($response->getStatusCode())->toBe(400)
        ->and(json_decode((string) $response->getContent(), true))->toHaveKey('message')
        ->and($renderer->rendered)->toBe([]);
})->with([
    'missing' => [[]],
    'unknown' => [['view' => 'unknownView']],
    'child product' => [['view' => 'childProduct']],
    'order list without ids' => [['view' => 'orderList']],
    'order with a bad id' => [['view' => 'order', 'orderId' => 'nope']],
    'array'   => [['view' => ['pluginSettings']]],
]);

it('answers 500 and logs when rendering fails', function () {
    $logger   = new RecordingLogger();
    $renderer = new FixedAdminViewRenderer('', new RuntimeException('boot failed'));

    $response = viewController($renderer, $logger)->view(
        Request::create('/api/_action/myparcel/view', 'GET', ['view' => 'pluginSettings'])
    );

    expect($response->getStatusCode())->toBe(500)
        ->and($logger->records)->toHaveCount(1);
});

it('returns the items and bulk actions of the result', function () {
    $renderer = new FixedAdminViewRenderer(new AdminViewResult('<b></b>', [CONTROLLER_ORDER_ID => '<i></i>'], [['action' => 'action_edit', 'label' => 'Edit']]));

    $response = viewController($renderer)->view(Request::create('/api/_action/myparcel/view', 'GET', ['view' => 'orderList', 'orderIds' => [CONTROLLER_ORDER_ID]]));
    $body     = (string) $response->getContent();

    expect(json_decode($body, true)['items'])->toBe([CONTROLLER_ORDER_ID => '<i></i>'])
        ->and($body)->toContain('"items":{"' . CONTROLLER_ORDER_ID . '"');
});

it('passes the order ids to the renderer', function () {
    $renderer = new FixedAdminViewRenderer();
    $request  = Request::create('/api/_action/myparcel/view', 'GET', ['view' => 'orderList', 'orderIds' => [CONTROLLER_ORDER_ID]]);

    viewController($renderer)->view($request);

    expect($renderer->rendered)->toEqual([new AdminViewRequest(AdminView::OrderList, [CONTROLLER_ORDER_ID])])
        ->and($request->attributes->get(AdminView::REQUEST_ATTRIBUTE))->toBe(AdminView::OrderList);
});

it('returns 404 when the order does not exist', function () {
    $renderer = new FixedAdminViewRenderer(error: AdminViewNotFoundException::forView(AdminView::Order, CONTROLLER_ORDER_ID));

    $response = viewController($renderer)->view(Request::create('/api/_action/myparcel/view', 'GET', ['view' => 'order', 'orderId' => CONTROLLER_ORDER_ID]));

    expect($response->getStatusCode())->toBe(404)
        ->and(json_decode((string) $response->getContent(), true))->toHaveKey('message');
});
