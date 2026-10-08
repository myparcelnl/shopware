<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Frontend\FrontendRenderService;
use MyParcelNL\Pdk\Frontend\Service\FrontendRenderService as PdkFrontendRenderService;

function renderTemplate(): string
{
    $service = (new ReflectionClass(FrontendRenderService::class))->newInstanceWithoutConstructor();

    return (new ReflectionMethod($service, 'getRenderTemplate'))->invoke($service);
}

it('is a pdk frontend render service', function () {
    expect(is_subclass_of(FrontendRenderService::class, PdkFrontendRenderService::class))->toBeTrue();
});

it('names the component in a data attribute', function () {
    expect(renderTemplate())
        ->toContain('data-pdk-component="__COMPONENT__"')
        ->toContain('data-pdk-context="__CONTEXT__"')
        ->toContain('id="__ID__"');
});

it('has no inline script, which the admin CSP blocks', function () {
    expect(renderTemplate())->not->toContain('<script');
});
