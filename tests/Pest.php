<?php

declare(strict_types=1);

/*
 * src/ is not scoped and names the bundled dependencies by their _MyParcel
 * prefixed names, while the tests run against the unscoped development vendor.
 * Resolve a prefixed name to the unscoped class the first time it is used.
 *
 * Parents and interfaces are aliased eagerly: PHP does not autoload for a type
 * check or a catch block, so `catch (_MyParcel\...\GuzzleException)` would never
 * match an exception whose interface alias had not been loaded yet.
 *
 * A built-in PHP class or interface (e.g. RuntimeException, Throwable) is
 * skipped: it is never renamed by php-scoper in the first place, so it has no
 * _MyParcel-prefixed counterpart to resolve to, and class_alias() refuses to
 * alias an internal class at all (`must be a user-defined class name`).
 */
spl_autoload_register(static function (string $class): void {
    $prefix = '_MyParcel\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $original = substr($class, strlen($prefix));

    if (!class_exists($original) && !interface_exists($original) && !trait_exists($original)) {
        return;
    }

    foreach ([$original, ...array_values(class_parents($original)), ...array_values(class_implements($original))] as $name) {
        if ((class_exists($name, false) || interface_exists($name, false)) && (new ReflectionClass($name))->isInternal()) {
            continue;
        }

        $alias = $prefix . $name;

        if (!class_exists($alias, false) && !interface_exists($alias, false)) {
            class_alias($name, $alias);
        }
    }
});
