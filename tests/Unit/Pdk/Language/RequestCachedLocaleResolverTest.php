<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Language\RequestCachedLocaleResolver;
use MyParcel\Shopware\Tests\Support\CountingLocaleResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

it('asks the inner resolver once per request', function () {
    $inner = new CountingLocaleResolver('nl-NL');
    $stack = new RequestStack();
    $stack->push(Request::create('/'));
    $resolver = new RequestCachedLocaleResolver($inner, $stack);

    $resolver->getLocale();
    $resolver->getLocale();

    expect($resolver->getLocale())->toBe('nl-NL')
        ->and($inner->calls)->toBe(1);
});

it('keeps a missing locale too', function () {
    $inner = new CountingLocaleResolver(null);
    $stack = new RequestStack();
    $stack->push(Request::create('/'));
    $resolver = new RequestCachedLocaleResolver($inner, $stack);

    $resolver->getLocale();

    expect($resolver->getLocale())->toBeNull()
        ->and($inner->calls)->toBe(1);
});

it('asks again for a new request', function () {
    $inner = new CountingLocaleResolver('de-DE');
    $stack = new RequestStack();
    $resolver = new RequestCachedLocaleResolver($inner, $stack);

    $stack->push(Request::create('/one'));
    $resolver->getLocale();
    $stack->pop();
    $stack->push(Request::create('/two'));
    $resolver->getLocale();

    expect($inner->calls)->toBe(2);
});

it('does not cache outside a request', function () {
    $inner    = new CountingLocaleResolver('en-GB');
    $resolver = new RequestCachedLocaleResolver($inner, new RequestStack());

    $resolver->getLocale();
    $resolver->getLocale();

    expect($inner->calls)->toBe(2);
});
