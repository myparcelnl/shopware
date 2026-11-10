<?php

declare(strict_types=1);

use MyParcel\Shopware\Subscriber\AutomaticOrderExportSubscriber;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;

/*
 * onTransition() boots the PDK, so a unit test cannot see it. AutomaticOrderExportTest
 * covers the export, and INT-2151 the order path in a Shopware installation.
 */

it('listens to the state machine transitions', function () {
    expect(AutomaticOrderExportSubscriber::getSubscribedEvents())
        ->toBe([StateMachineTransitionEvent::class => 'onTransition']);
});
