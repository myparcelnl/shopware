<?php

declare(strict_types=1);

use MyParcel\Shopware\Tests\Support\HttpBridgeFactory;
use MyParcel\Shopware\Tests\Support\RecordingLogger;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * In the test run, _MyParcel\Symfony\… is an alias of Symfony\…, so these tests
 * prove that the data is copied, not that the class boundary is correct. The
 * manual test on the scoped build proves the boundary.
 *
 * The alias is made on first autoload, and neither a parameter type check nor
 * instanceof autoloads, so load the aliases the bridge tests against up front.
 */
class_exists('_MyParcel\\Symfony\\Component\\HttpFoundation\\Response');
class_exists('_MyParcel\\Symfony\\Component\\HttpFoundation\\BinaryFileResponse');

it('copies method, query, body, cookies, server and raw content to the pdk request', function () {
    $request = Request::create(
        'https://shop.test/api/_action/myparcel/pdk?action=fetchContext',
        'POST',
        ['form' => 'value'],
        ['session' => 'abc'],
        [],
        ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        '{"data":{"orders":[1]}}'
    );

    $converted = HttpBridgeFactory::create(new RecordingLogger())->toPdkRequest($request);

    expect($converted->getMethod())->toBe('POST')
        ->and($converted->query->get('action'))->toBe('fetchContext')
        ->and($converted->get('action'))->toBe('fetchContext')
        ->and($converted->request->get('form'))->toBe('value')
        ->and($converted->cookies->get('session'))->toBe('abc')
        ->and($converted->headers->get('X-Requested-With'))->toBe('XMLHttpRequest')
        ->and($converted->getRequestUri())->toBe('/api/_action/myparcel/pdk?action=fetchContext')
        ->and($converted->getContent())->toBe('{"data":{"orders":[1]}}');
});

it('does not copy attributes or files', function () {
    $request = Request::create('https://shop.test/myparcel/pdk');
    $request->attributes->set('sw-context', new stdClass());

    $converted = HttpBridgeFactory::create(new RecordingLogger())->toPdkRequest($request);

    expect($converted->attributes->all())->toBe([])
        ->and($converted->files->all())->toBe([]);
});

it('returns content, status and headers unchanged', function () {
    $pdkResponse = new JsonResponse(['data' => ['ok' => true]], 207, ['X-Multi' => ['a', 'b']]);
    $pdkResponse->headers->setCookie(Cookie::create('pdk', 'yes'));

    $response = HttpBridgeFactory::create(new RecordingLogger())->toShopwareResponse($pdkResponse);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(207)
        ->and($response->getContent())->toBe('{"data":{"ok":true}}')
        ->and($response->headers->get('Content-Type'))->toBe('application/json')
        ->and($response->headers->all('x-multi'))->toBe(['a', 'b'])
        ->and($response->headers->getCookies()[0]->getName())->toBe('pdk');
});

it('keeps a file response a file response, including delete after send', function () {
    $path = tempnam(sys_get_temp_dir(), 'pdk');
    file_put_contents($path, 'zip');

    $pdkResponse = new BinaryFileResponse($path, 200, ['Content-Type' => 'application/zip']);
    $pdkResponse->deleteFileAfterSend();

    $response = HttpBridgeFactory::create(new RecordingLogger())->toShopwareResponse($pdkResponse);

    expect($response)->toBeInstanceOf(BinaryFileResponse::class)
        ->and($response->getFile()->getPathname())->toBe($path)
        ->and($response->headers->get('Content-Type'))->toBe('application/zip');

    ob_start();
    $response->sendContent();
    $sent = ob_get_clean();

    expect($sent)->toBe('zip')
        ->and(file_exists($path))->toBeFalse();
});

it('returns the result of a call that succeeds', function () {
    $logger   = new RecordingLogger();
    $response = HttpBridgeFactory::create($logger)->run(fn () => new Response('ok', 202));

    expect($response->getStatusCode())->toBe(202)
        ->and($logger->records)->toBe([]);
});

it('turns an exception outside the pdk into a logged json 500', function () {
    $logger   = new RecordingLogger();
    $response = HttpBridgeFactory::create($logger)->run(function (): Response {
        throw new RuntimeException('Boot failed');
    });

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(500)
        ->and(json_decode((string) $response->getContent(), true))->toBe(['message' => 'Boot failed'])
        ->and($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe(LogLevel::ERROR)
        ->and($logger->records[0]['context']['exception'])->toBeInstanceOf(RuntimeException::class);
});
