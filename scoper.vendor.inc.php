<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

/**
 * Scopes vendor dependencies. Unlike scoper.inc.php this DOES prefix
 * Symfony/Psr/etc: the PDK's bundled symfony/http-foundation (^6) must live
 * under _MyParcelNL\ so it can coexist with Shopware's Symfony 7.x runtime.
 */
return [
    'prefix' => '_MyParcelNL',

    'finders' => [
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.lock/')
            ->exclude([
                'test',
                'tests',
                'Tests',
                'vendor-bin',
            ])
            ->in('vendor'),
    ],

    'exclude-namespaces' => [
        // Exclude global namespace
        '/^$/',
        'Composer',
        'MyParcelNL',
    ],

    'exclude-files' => [
        'vendor/php-di/php-di/src/Compiler/Template.php',
    ],

    'patchers' => [
        // php-scoper passes an absolute path, so match on the suffix.
        static function (string $filePath, string $prefix, string $contents): string {
            if (!str_ends_with($filePath, 'php-di/php-di/src/functions.php')) {
                return $contents;
            }

            // Guards against php-scoper leaving the string literal inside
            // function_exists() unprefixed: if another plugin loads DI\value() first,
            // our guard would see it as already defined and skip defining
            // _MyParcelNL\DI\value() — causing a fatal error. Inherited from
            // myparcelnl/woocommerce; php-scoper 0.18 already prefixes these literals
            // itself, so this is a no-op there and kept only as a safety net.
            return str_replace(
                "function_exists('DI\\",
                "function_exists('{$prefix}\\DI\\",
                $contents
            );
        },
    ],
];
