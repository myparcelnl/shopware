<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Cache\CacheDirectory;

/*
 * Root can write to any directory, so the read-only cases only mean something
 * for another user. CI runs the suite as root in a container.
 */
function runsAsRoot(): bool
{
    return function_exists('posix_getuid') && 0 === posix_getuid();
}

function temporaryDirectory(): string
{
    $directory = sys_get_temp_dir() . '/pdk-cache-test-' . bin2hex(random_bytes(4));
    mkdir($directory);

    return $directory;
}

it('accepts an existing writable directory', function () {
    $directory = temporaryDirectory();

    expect(CacheDirectory::isWritable($directory))->toBeTrue();

    rmdir($directory);
});

it('accepts a missing directory whose parent is writable', function () {
    $parent = temporaryDirectory();

    expect(CacheDirectory::isWritable($parent . '/.cache'))->toBeTrue()
        ->and(is_dir($parent . '/.cache'))->toBeFalse();

    rmdir($parent);
});

it('rejects an existing directory that is not writable', function () {
    $directory = temporaryDirectory();
    chmod($directory, 0555);

    expect(CacheDirectory::isWritable($directory))->toBeFalse();

    chmod($directory, 0755);
    rmdir($directory);
})->skip(runsAsRoot(), 'root can write to any directory');

it('rejects a missing directory whose parent is not writable', function () {
    $parent = temporaryDirectory();
    chmod($parent, 0555);

    expect(CacheDirectory::isWritable($parent . '/.cache'))->toBeFalse();

    chmod($parent, 0755);
    rmdir($parent);
})->skip(runsAsRoot(), 'root can write to any directory');

it('rejects a directory whose parent does not exist', function () {
    expect(CacheDirectory::isWritable(sys_get_temp_dir() . '/pdk-missing-' . bin2hex(random_bytes(4)) . '/.cache'))
        ->toBeFalse();
});
