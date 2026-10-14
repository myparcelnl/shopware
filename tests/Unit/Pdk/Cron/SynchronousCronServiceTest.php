<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Cron\SynchronousCronService;
use MyParcelNL\Pdk\Base\Contract\CronServiceInterface;

it('is a pdk cron service', function () {
    expect(new SynchronousCronService())->toBeInstanceOf(CronServiceInterface::class);
});

it('runs a dispatched callback at once with its arguments', function () {
    $received = null;

    (new SynchronousCronService())->dispatch(function (string $a, int $b) use (&$received) {
        $received = [$a, $b];
    }, 'x', 2);

    expect($received)->toBe(['x', 2]);
});

it('runs an object method given as array, the way the webhook manager dispatches', function () {
    $target = new class {
        public array $calls = [];

        public function process(string $value): void
        {
            $this->calls[] = $value;
        }
    };

    (new SynchronousCronService())->dispatch([$target, 'process'], 'hook');

    expect($target->calls)->toBe(['hook']);
});

it('refuses a callback that cannot be called, and names it', function () {
    (new SynchronousCronService())->dispatch('this_function_does_not_exist');
})->throws(InvalidArgumentException::class, 'The cron callback this_function_does_not_exist cannot be called.');

it('names an object method callback that cannot be called', function () {
    (new SynchronousCronService())->dispatch([new stdClass(), 'missing']);
})->throws(InvalidArgumentException::class, 'The cron callback stdClass::missing cannot be called.');

it('names the type of a callback that is neither a string nor an array', function () {
    (new SynchronousCronService())->dispatch(42);
})->throws(InvalidArgumentException::class, 'The cron callback int cannot be called.');

it('refuses to schedule, because there is no queue yet', function () {
    (new SynchronousCronService())->schedule(fn () => null, time() + 60);
})->throws(RuntimeException::class);
