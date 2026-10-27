<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Frontend\AdminViewNotFoundException;
use MyParcel\Shopware\Pdk\Frontend\AdminViewRenderer;
use MyParcel\Shopware\Pdk\Frontend\ViewService;
use MyParcel\Shopware\Pdk\Order\Mapper\AddressMapper;
use MyParcel\Shopware\Pdk\Order\Mapper\MoneyConverter;
use MyParcel\Shopware\Pdk\Order\Mapper\PdkOrderMapper;
use MyParcel\Shopware\Pdk\Order\Repository\PdkOrderRepository;
use MyParcel\Shopware\Pdk\PdkBootInterface;
use MyParcel\Shopware\Pdk\Product\Mapper\PdkProductMapper;
use MyParcel\Shopware\Pdk\Product\Repository\PdkProductRepository;
use MyParcel\Shopware\Pdk\View\AdminView;
use MyParcel\Shopware\Pdk\View\AdminViewRequest;
use MyParcel\Shopware\Pdk\View\RequestViewResolver;
use MyParcel\Shopware\Tests\Support\Fixtures;
use MyParcel\Shopware\Tests\Support\InMemoryOrderStorage;
use MyParcel\Shopware\Tests\Support\InMemoryProductStorage;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\App\Order\Contract\PdkProductRepositoryInterface;
use MyParcelNL\Pdk\Base\Pdk;
use MyParcelNL\Pdk\Base\Service\CurrencyService;
use MyParcelNL\Pdk\Base\Service\WeightService;
use MyParcelNL\Pdk\Frontend\Contract\ViewServiceInterface;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

const RENDERER_ORDER_ID   = '0190a1b2c3d4e5f60718293a4b5c6d01';
const RENDERER_PRODUCT_ID = '0190a1b2c3d4e5f60718293a4b5c6e01';
const RENDERER_VARIANT_ID = '0190a1b2c3d4e5f60718293a4b5c6e02';

/**
 * Boots the PDK mock environment with Shopware's repositories on in-memory
 * storage and the view service on a real request stack. Returns the renderer
 * and the request the view lives on.
 *
 * @return array{AdminViewRenderer, Request}
 */
function rendererWithStorage(?AdminView $view): array
{
    $currency = new CurrencyService();
    $orders   = new InMemoryOrderStorage();
    $products = new InMemoryProductStorage();

    $orders->snapshots[RENDERER_ORDER_ID] = Fixtures::snapshot('order-nl');

    $parent                                = rendererProductSnapshot(RENDERER_PRODUCT_ID, null, null);
    $products->snapshots[RENDERER_PRODUCT_ID] = $parent;
    $products->snapshots[RENDERER_VARIANT_ID] = rendererProductSnapshot(RENDERER_VARIANT_ID, RENDERER_PRODUCT_ID, $parent);

    $request = new Request();
    $stack   = new RequestStack();
    $stack->push($request);

    if (null !== $view) {
        $request->attributes->set(AdminView::REQUEST_ATTRIBUTE, $view);
    }

    TestPdk::boot([
        ViewServiceInterface::class          => new ViewService(new RequestViewResolver($stack)),
        PdkOrderRepositoryInterface::class   => new PdkOrderRepository(
            new MemoryCacheStorage(),
            $orders,
            new PdkOrderMapper(new AddressMapper(), new MoneyConverter($currency), new PdkProductMapper($currency, new WeightService()))
        ),
        PdkProductRepositoryInterface::class => new PdkProductRepository(
            new MemoryCacheStorage(),
            $products,
            new PdkProductMapper($currency, new WeightService())
        ),
    ]);

    $booter = new class() implements PdkBootInterface {
        public function boot(): Pdk
        {
            return \MyParcelNL\Pdk\Facade\Pdk::getPdkInstance();
        }
    };

    return [new AdminViewRenderer($booter, $stack), $request];
}

/**
 * @param  null|array<string, mixed> $parent
 *
 * @return array<string, mixed>
 */
function rendererProductSnapshot(string $id, ?string $parentId, ?array $parent): array
{
    return [
        'id' => $id, 'parentId' => $parentId, 'productNumber' => 'SW-' . substr($id, -2), 'ean' => null, 'name' => 'Testproduct',
        'priceGross' => 12.10, 'weight' => 0.25, 'width' => null, 'height' => null, 'length' => null,
        'type' => 'physical', 'customFields' => [], 'parent' => $parent,
    ];
}

it('renders one item per order', function () {
    [$renderer] = rendererWithStorage(AdminView::OrderList);

    $result = $renderer->render(new AdminViewRequest(AdminView::OrderList, [RENDERER_ORDER_ID]));

    expect($result->html)->not->toBe('')
        ->and(array_keys($result->items))->toBe([RENDERER_ORDER_ID])
        ->and($result->items[RENDERER_ORDER_ID])->toContain("render('OrderListItem'");
});

it('skips orders that do not exist', function () {
    [$renderer] = rendererWithStorage(AdminView::OrderList);

    $result = $renderer->render(new AdminViewRequest(AdminView::OrderList, [RENDERER_ORDER_ID, '0190a1b2c3d4e5f60718293a4b5c6dff']));

    expect(array_keys($result->items))->toBe([RENDERER_ORDER_ID]);
});

it('adds the bulk actions of the order mode', function () {
    [$renderer] = rendererWithStorage(AdminView::OrderList);

    $result = $renderer->render(new AdminViewRequest(AdminView::OrderList, [RENDERER_ORDER_ID]));
    $actions = array_column($result->bulkActions, 'action');

    expect($actions)->toBe(\MyParcelNL\Pdk\Facade\Pdk::get('bulkActions'))
        ->and($result->bulkActions)->not->toBe([])
        ->and($result->bulkActions[0]['label'])->toBe(\MyParcelNL\Pdk\Facade\Language::translate($actions[0]));
});

it('renders a product with its settings', function () {
    [$renderer, $request] = rendererWithStorage(AdminView::Product);

    $result = $renderer->render(new AdminViewRequest(AdminView::Product, [RENDERER_PRODUCT_ID]));

    expect($result->html)->toContain("render('ProductSettings'")
        ->and($request->attributes->get(AdminView::REQUEST_ATTRIBUTE))->toBe(AdminView::Product);
});

it('renders a variant as a child product', function () {
    [$renderer, $request] = rendererWithStorage(AdminView::Product);

    $result = $renderer->render(new AdminViewRequest(AdminView::Product, [RENDERER_VARIANT_ID]));

    expect($result->html)->toContain("render('ChildProductSettings'")
        ->and($request->attributes->get(AdminView::REQUEST_ATTRIBUTE))->toBe(AdminView::ChildProduct);
});

it('renders an order', function () {
    [$renderer] = rendererWithStorage(AdminView::Order);

    $result = $renderer->render(new AdminViewRequest(AdminView::Order, [RENDERER_ORDER_ID]));

    expect($result->html)->toContain("render('OrderBox'")
        ->and($result->items)->toBe([]);
});

it('throws not found for a missing order', function () {
    [$renderer] = rendererWithStorage(AdminView::Order);

    expect(fn () => $renderer->render(new AdminViewRequest(AdminView::Order, ['0190a1b2c3d4e5f60718293a4b5c6dff'])))
        ->toThrow(AdminViewNotFoundException::class, 'order');
});
