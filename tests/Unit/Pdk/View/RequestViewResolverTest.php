<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Pdk\View\RequestViewResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

it('reads the view from the main request', function () {
    $request = Request::create('/api/_action/myparcel/view');
    $request->attributes->set(AdminView::REQUEST_ATTRIBUTE, AdminView::PluginSettings);
    $stack = new RequestStack();
    $stack->push($request);

    expect((new RequestViewResolver($stack))->getView())->toBe(AdminView::PluginSettings);
});

it('returns null when the request has no view', function () {
    $stack = new RequestStack();
    $stack->push(Request::create('/api/_action/myparcel/pdk'));

    expect((new RequestViewResolver($stack))->getView())->toBeNull();
});

it('returns null outside a request', function () {
    expect((new RequestViewResolver(new RequestStack()))->getView())->toBeNull();
});

it('ignores a value that is not a view', function () {
    $request = Request::create('/');
    $request->attributes->set(AdminView::REQUEST_ATTRIBUTE, 'pluginSettings');
    $stack = new RequestStack();
    $stack->push($request);

    expect((new RequestViewResolver($stack))->getView())->toBeNull();
});
