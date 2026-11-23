<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

/**
 * Counts how often a class was created from its name. A test passes its name as
 * input, to prove that the input never reaches a `new $value()`.
 */
final class InstantiationProbe
{
    public static int $created = 0;

    public function __construct()
    {
        ++self::$created;
    }
}
