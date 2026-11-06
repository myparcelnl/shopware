<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Currency\EuroCurrency;
use MyParcel\Shopware\Pdk\Frontend\AdminViewNotFoundException;
use MyParcel\Shopware\Pdk\Frontend\AdminViewRenderer;
use MyParcel\Shopware\Pdk\Frontend\FrontendRenderService;
use MyParcel\Shopware\Pdk\Frontend\ViewService;
use MyParcel\Shopware\Pdk\Order\Mapper\AddressMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Pdk\Order\Mapper\PdkOrderMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\StreetSplitter;
use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderRepository;
use MyParcel\Shopware\Pdk\Order\Writer\OrderDataWriter;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\Repository\PdkProductRepository;
use MyParcel\Shopware\Pdk\Product\Writer\ProductSettingsWriter;
use MyParcel\Shopware\Pdk\View\AdminScreen;
use MyParcel\Shopware\Pdk\View\AdminViewRequest;
use MyParcel\Shopware\Tests\Support\Entities;
use MyParcel\Shopware\Tests\Support\FakeEntityRepository;
use MyParcel\Shopware\Tests\Support\FixedLocaleResolver;
use MyParcel\Shopware\Tests\Support\FixedOrderStatusService;
use MyParcel\Shopware\Tests\Support\FixedUrlResolver;
use MyParcel\Shopware\Tests\Support\InMemoryConfigStorage;
use MyParcel\Shopware\Tests\Support\RecordingConnection;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Contract\PdkProductRepositoryInterface;
use MyParcelNL\Pdk\Base\PdkBootstrapper as PdkBaseBootstrapper;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Facade\Language;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Frontend\Contract\FrontendRenderServiceInterface;
use MyParcelNL\Pdk\Frontend\Contract\ViewServiceInterface;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function DI\autowire;

const RENDERER_VARIANT_ID = '0190a1b2c3d4e5f60718293a4b5c0002';

/**
 * Marks the PDK as booted with the mock PDK of the test, so the real
 * PdkInitializer::boot() returns that PDK instead of building one from the
 * Shopware container.
 */
function markPdkBooted(bool $booted): void
{
    (new ReflectionProperty(PdkBaseBootstrapper::class, 'initialized'))->setValue(null, $booted);
    (new ReflectionProperty(PdkBaseBootstrapper::class, 'pdk'))->setValue(null, $booted ? Pdk::getPdkInstance() : null);
}

/**
 * A renderer on one Dutch order, one product and one variant of that product.
 * Returns the renderer and the request that holds the screen.
 *
 * @return array{AdminViewRenderer, Request}
 */
function adminViewRenderer(): array
{
    $request      = new Request();
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $currency = new CurrencyService();
    $euro     = new EuroCurrency(FakeEntityRepository::with(Entities::currency('EUR', 1.0)));
    $parent   = Entities::product();
    $variant  = Entities::product(['id' => RENDERER_VARIANT_ID, 'parentId' => Entities::PRODUCT_ID, 'parent' => $parent]);
    $products = new PdkProductRepository(
        new MemoryCacheStorage(),
        FakeEntityRepository::with($parent, $variant),
        $euro,
        new PdkProductMapper($currency, new WeightService()),
        new ProductSettingsWriter(FakeEntityRepository::with())
    );
    $viewService = new ViewService($requestStack);

    TestPdk::boot([
        FrontendRenderServiceInterface::class => autowire(FrontendRenderService::class),
        ViewServiceInterface::class           => $viewService,
        PdkProductRepositoryInterface::class  => $products,
        PdkOrderRepositoryInterface::class    => new PdkOrderRepository(
            new MemoryCacheStorage(),
            FakeEntityRepository::with(Entities::order()),
            $euro,
            $products,
            new PdkOrderMapper(new AddressMapper(new StreetSplitter()), new MoneyConverter($currency)),
            new OrderDataWriter(FakeEntityRepository::with(), FakeEntityRepository::with(), new RecordingConnection())
        ),
    ]);

    markPdkBooted(true);

    $initializer = new PdkInitializer(
        new RecordingLogger(),
        new InMemoryConfigStorage(),
        new FixedLocaleResolver(null),
        new FixedUrlResolver([]),
        FakeEntityRepository::with(),
        FakeEntityRepository::with(),
        $euro,
        new OrderDataWriter(FakeEntityRepository::with(), FakeEntityRepository::with(), new RecordingConnection()),
        new ProductSettingsWriter(FakeEntityRepository::with()),
        $viewService,
        new FixedOrderStatusService(),
        '0.0.0-test',
        'https://shop.test',
        true
    );

    return [new AdminViewRenderer($initializer, $requestStack), $request];
}

afterEach(fn () => markPdkBooted(false));

it('renders one item per order and leaves unknown orders out', function () {
    [$renderer, $request] = adminViewRenderer();

    $result = $renderer->render(new AdminViewRequest(AdminScreen::OrderList, [Entities::ORDER_ID, Entities::id()]));

    expect($result->html)->not->toBe('')
        ->and(array_keys($result->items))->toBe([Entities::ORDER_ID])
        ->and($result->items[Entities::ORDER_ID])->toContain('data-pdk-component="OrderListItem"')
        ->and($request->attributes->get(AdminScreen::REQUEST_ATTRIBUTE))->toBe(AdminScreen::OrderList);
});

it('adds the bulk actions of the order mode with their labels', function () {
    [$renderer] = adminViewRenderer();

    $result  = $renderer->render(new AdminViewRequest(AdminScreen::OrderList, [Entities::ORDER_ID]));
    $actions = array_column($result->bulkActions, 'action');

    expect($actions)->toBe(Pdk::get('bulkActions'))
        ->and($result->bulkActions)->not->toBe([])
        ->and($result->bulkActions[0]['label'])->toBe(Language::translate($actions[0]));
});

it('renders an order', function () {
    [$renderer] = adminViewRenderer();

    $result = $renderer->render(new AdminViewRequest(AdminScreen::Order, [Entities::ORDER_ID]));

    expect($result->html)->toContain('data-pdk-component="OrderBox"')
        ->and($result->items)->toBe([]);
});

it('throws not found for a missing order', function () {
    [$renderer] = adminViewRenderer();

    expect(fn () => $renderer->render(new AdminViewRequest(AdminScreen::Order, [Entities::id()])))
        ->toThrow(AdminViewNotFoundException::class, 'order');
});

it('throws not found for a missing product', function () {
    [$renderer] = adminViewRenderer();

    expect(fn () => $renderer->render(new AdminViewRequest(AdminScreen::Product, [Entities::id()])))
        ->toThrow(AdminViewNotFoundException::class, 'product');
});

it('renders a product with its settings', function () {
    [$renderer, $request] = adminViewRenderer();

    $result = $renderer->render(new AdminViewRequest(AdminScreen::Product, [Entities::PRODUCT_ID]));

    expect($result->html)->toContain('data-pdk-component="ProductSettings"')
        ->and($request->attributes->get(AdminScreen::REQUEST_ATTRIBUTE))->toBe(AdminScreen::Product);
});

it('renders a variant as a child product', function () {
    [$renderer, $request] = adminViewRenderer();

    $result = $renderer->render(new AdminViewRequest(AdminScreen::Product, [RENDERER_VARIANT_ID]));

    expect($result->html)->toContain('data-pdk-component="ChildProductSettings"')
        ->and($request->attributes->get(AdminScreen::REQUEST_ATTRIBUTE))->toBe(AdminScreen::ChildProduct);
});

it('renders the plugin settings', function () {
    [$renderer] = adminViewRenderer();

    expect($renderer->render(new AdminViewRequest(AdminScreen::PluginSettings, []))->html)
        ->toContain('data-pdk-component="PluginSettings"');
});
