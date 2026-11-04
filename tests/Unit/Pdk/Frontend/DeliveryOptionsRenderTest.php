<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Frontend\FrontendRenderService;
use MyParcel\Shopware\Pdk\Frontend\ViewService;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\App\Cart\Model\PdkCart;
use MyParcelNL\Pdk\Base\FileSystemInterface;
use MyParcelNL\Pdk\Base\Support\Collection;
use MyParcelNL\Pdk\Context\Contract\ContextServiceInterface;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Settings\Model\CheckoutSettings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function MyParcelNL\Pdk\Tests\factory;

/**
 * Our render service in the mock PDK, on the checkout confirm page or not.
 */
function checkoutRenderService(bool $onCheckout = true): FrontendRenderService
{
    $requestStack = new RequestStack();
    $requestStack->push(new Request([], [], ['_route' => $onCheckout ? ViewService::CHECKOUT_ROUTE : 'frontend.home.page']));

    return new FrontendRenderService(
        Pdk::get(ContextServiceInterface::class),
        new ViewService($requestStack),
        Pdk::get(FileSystemInterface::class)
    );
}

function deliverableCart(string $shippingMethodId = 'sm-standard', bool $deliverable = true): PdkCart
{
    return new PdkCart([
        'externalIdentifier' => 'token',
        'lines'              => [['quantity' => 1, 'product' => ['externalIdentifier' => 'p-1', 'isDeliverable' => $deliverable]]],
        'shippingMethod'     => ['id' => $shippingMethodId, 'shippingAddress' => ['cc' => 'NL']],
    ]);
}

/**
 * The checkout context from the data-context attribute of the rendered HTML.
 *
 * @return array<string, mixed>
 */
function renderedCheckoutContext(string $html): array
{
    preg_match('/data-context="([^"]*)"/', $html, $matches);

    return json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES), true)['checkout'] ?? [];
}

beforeEach(fn () => TestPdk::boot());

it('renders the wrapper with the checkout context and the mount point of the widget', function () {
    $html = checkoutRenderService()->renderDeliveryOptions(deliverableCart());

    expect($html)
        ->toContain('id="mypa-delivery-options-wrapper"')
        ->toContain('<div id="myparcel-delivery-options"></div>')
        ->not->toContain('<script')
        ->and(renderedCheckoutContext($html)['settings']['hasDeliveryOptions'])->toBeTrue()
        ->and(renderedCheckoutContext($html)['config'])->toHaveKey('carrierSettings');
});

it('renders nothing outside the checkout', function () {
    expect(checkoutRenderService(false)->renderDeliveryOptions(deliverableCart()))->toBe('');
});

it('renders nothing when the merchant turned the delivery options off', function () {
    factory(CheckoutSettings::class)->withEnableDeliveryOptions(false)->store();

    expect(checkoutRenderService()->renderDeliveryOptions(deliverableCart()))->toBe('');
});

it('renders nothing for a cart without deliverable products', function () {
    expect(checkoutRenderService()->renderDeliveryOptions(deliverableCart('sm-standard', false)))->toBe('');
});

it('renders nothing for a shipping method that the merchant linked to no package type', function () {
    factory(CheckoutSettings::class)
        ->withAllowedShippingMethods(new Collection(['package' => ['sm-standard']]))
        ->store();

    expect(checkoutRenderService()->renderDeliveryOptions(deliverableCart('sm-pickup-in-store')))->toBe('')
        ->and(checkoutRenderService()->renderDeliveryOptions(deliverableCart('sm-standard')))->not->toBe('');
});

it('adds the custom css of the merchant without letting it close the style element', function () {
    factory(CheckoutSettings::class)
        ->withDeliveryOptionsCustomCss('.x { color: red; }</style><script>alert(1)</script>')
        ->store();

    expect(checkoutRenderService()->renderDeliveryOptions(deliverableCart()))
        ->toContain('<style>.x { color: red; }<\/style><script>alert(1)<\/script></style>');
});
