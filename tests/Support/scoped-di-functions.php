<?php

declare(strict_types=1);

/*
 * src/ calls the php-di helper functions by their _MyParcel prefixed names. The
 * autoloader in tests/Pest.php aliases prefixed classes, but PHP does not
 * autoload functions, so these forward the prefixed names to the unscoped php-di
 * of the development install.
 */

namespace _MyParcel\DI;

use DI\Definition\Helper\AutowireDefinitionHelper;
use DI\Definition\Helper\FactoryDefinitionHelper;
use DI\Definition\Reference;
use DI\Definition\ValueDefinition;

function autowire(?string $className = null): AutowireDefinitionHelper
{
    return \DI\autowire($className);
}

function factory(mixed $factory): FactoryDefinitionHelper
{
    return \DI\factory($factory);
}

function value(mixed $value): ValueDefinition
{
    return \DI\value($value);
}

function get(string $entryName): Reference
{
    return \DI\get($entryName);
}
