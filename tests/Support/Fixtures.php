<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

/**
 * Reads the JSON snapshots in tests/Fixtures/Snapshot.
 */
final class Fixtures
{
    /**
     * Returns the decoded snapshot with this file name, without ".json".
     *
     * @return array<string, mixed>
     */
    public static function snapshot(string $name): array
    {
        $file = dirname(__DIR__) . '/Fixtures/Snapshot/' . $name . '.json';

        return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }
}
