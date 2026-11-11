<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Frontend\FrontendRenderService;
use MyParcel\Shopware\Pdk\Frontend\ViewService;
use MyParcel\Shopware\Pdk\View\AdminScreen;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Model\PdkProduct;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Frontend\Contract\FrontendRenderServiceInterface;
use MyParcelNL\Pdk\Frontend\Contract\ViewServiceInterface;
use MyParcelNL\Pdk\Frontend\Service\FrontendRenderService as PdkFrontendRenderService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function DI\autowire;

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

it('gives the variant settings the product settings view context', function () {
    $request = new Request();
    $request->attributes->set(AdminScreen::REQUEST_ATTRIBUTE, AdminScreen::ChildProduct);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    TestPdk::boot([
        FrontendRenderServiceInterface::class => autowire(FrontendRenderService::class),
        ViewServiceInterface::class           => new ViewService($requestStack),
    ]);

    $variant = new PdkProduct([
        'externalIdentifier' => '0190a1b2c3d4e5f60718293a4b5c6e02',
        'parent'             => new PdkProduct(['externalIdentifier' => '0190a1b2c3d4e5f60718293a4b5c6e01']),
    ]);

    $html = Pdk::get(FrontendRenderServiceInterface::class)->renderChildProductSettings($variant);

    preg_match('/data-pdk-context="([^"]*)"/', $html, $matches);
    $context = json_decode(html_entity_decode($matches[1] ?? ''), true);

    expect($html)->toContain('data-pdk-component="ChildProductSettings"')
        ->and($context)->toBeArray()
        ->toHaveKey('productData')
        ->toHaveKey('productSettingsView');
});

it('refuses a product without a parent', function () {
    TestPdk::boot([FrontendRenderServiceInterface::class => autowire(FrontendRenderService::class)]);

    expect(fn () => Pdk::get(FrontendRenderServiceInterface::class)->renderChildProductSettings(new PdkProduct()))
        ->toThrow(InvalidArgumentException::class);
});
