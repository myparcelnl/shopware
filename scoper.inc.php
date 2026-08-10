<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

// For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md
return [
    'prefix' => '_MyParcelNL',

    'finders' => [
        Finder::create()
            ->append([
                'composer.json',
            ]),
        Finder::create()
            ->files()
            ->in(['src']),
    ],

    'exclude-namespaces' => [
        // Exclude global namespace
        '/^$/',
        'Composer',
        'MyParcelNL',
        // Host runtime namespaces: plugin src must keep referencing the real
        // Shopware/Symfony classes, only the PDK's bundled copies are prefixed.
        'Shopware',
        'Symfony',
        'Psr',
        'Monolog',
        'Twig',
        'Doctrine',
    ],
];
