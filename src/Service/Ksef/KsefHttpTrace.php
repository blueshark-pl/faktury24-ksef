<?php
declare(strict_types=1);

namespace App\Service\Ksef;

final class KsefHttpTrace
{
    /** @var array<int, array<string, mixed>> */
    private static array $entries = [];

    /** @param array<string, mixed> $entry */
    public static function add(array $entry): void
    {
        self::$entries[] = $entry;
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return self::$entries;
    }

    public static function clear(): void
    {
        self::$entries = [];
    }
}
