<?php

declare(strict_types=1);

use MyParcel\Shopware\Admin\AdminAssets;
use MyParcel\Shopware\Controller\AdminViewController;
use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Tests\Support\FixedAdminViewRenderer;
use MyParcel\Shopware\Tests\Support\HttpBridgeFactory;
use MyParcel\Shopware\Tests\Support\PrefixAssetUrlResolver;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use Symfony\Component\HttpFoundation\Request;

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
            'html'    => '<span id="myparcel-pdk-boot"></span>',
            'scripts' => ['https://shop.test/bundles/myparcelshopware/pdk/admin.iife.js?1'],
            'styles'  => ['https://shop.test/bundles/myparcelshopware/pdk/admin.css?1'],
        ])
        ->and($renderer->rendered)->toBe([AdminView::PluginSettings])
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
    'unknown' => [['view' => 'orderList']],
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
