<?php

declare(strict_types=1);

use MyParcel\Shopware\Checkout\SalesChannelContextHolder;
use MyParcel\Shopware\Pdk\Tax\TaxService;
use MyParcel\Shopware\Tests\Support\Checkout;
use MyParcel\Shopware\Tests\Support\FixedCartLoader;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

function taxService(?SalesChannelContext $context, ?Cart $cart = null): TaxService
{
    $holder = new SalesChannelContextHolder();

    if (null !== $context) {
        $holder->set($context);
    }

    return new TaxService($holder, new FixedCartLoader($cart ?? Checkout::cart()));
}

it('adds the tax of the shipping costs for a gross context', function () {
    expect(taxService(Checkout::context())->getShippingDisplayPrice(5.0))->toBe(6.05);
});

it('weighs the tax rules of the shipping costs', function () {
    $cart = Checkout::cart(['taxRules' => new TaxRuleCollection([new TaxRule(21.0, 60.0), new TaxRule(9.0, 40.0)])]);

    // 21 % of 60 % plus 9 % of 40 % is 16.2 %.
    expect(taxService(Checkout::context(), $cart)->getShippingDisplayPrice(10.0))->toBe(11.62);
});

it('keeps the price for a net or tax-free context', function (string $taxState) {
    expect(taxService(Checkout::context(['taxState' => $taxState]))->getShippingDisplayPrice(5.0))->toBe(5.0);
})->with([CartPrice::TAX_STATE_NET, CartPrice::TAX_STATE_FREE]);

it('keeps the price outside the storefront, where no context is set', function () {
    expect(taxService(null)->getShippingDisplayPrice(5.0))->toBe(5.0);
});

it('keeps the price when the cart has no delivery yet', function () {
    expect(taxService(Checkout::context(), new Cart(Checkout::TOKEN))->getShippingDisplayPrice(5.0))->toBe(5.0);
});
