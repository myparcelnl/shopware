<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Account\OrderModeNotice;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\Account\Service\PdkAccountFeaturesService;
use MyParcelNL\Pdk\Facade\Notifications;

it('warns the merchant when the effective order mode is v2', function () {
    TestPdk::boot();
    TestPdk::useSubscriptionFeatures([PdkAccountFeaturesService::FEATURE_ORDER_MANAGEMENT]);

    (new OrderModeNotice())->add();

    $notifications = Notifications::all()
        ->toArrayWithoutNull();

    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]['variant'])->toBe('warning')
        ->and($notifications[0]['category'])->toBe('general')
        ->and($notifications[0]['title'])->toBe(OrderModeNotice::TITLE_KEY)
        ->and($notifications[0]['content'])->toContain(OrderModeNotice::MESSAGE_KEY);
});

it('says nothing in shipments mode', function () {
    TestPdk::boot();
    TestPdk::useSubscriptionFeatures([]);

    (new OrderModeNotice())->add();

    expect(Notifications::isEmpty())->toBeTrue();
});

it('says nothing in order mode v1', function () {
    TestPdk::boot();
    TestPdk::useSubscriptionFeatures([PdkAccountFeaturesService::FEATURE_LEGACY_ORDER_MANAGEMENT]);

    (new OrderModeNotice())->add();

    expect(Notifications::isEmpty())->toBeTrue();
});
