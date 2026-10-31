<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Fixtures;

use stdClass;

/**
 * A JSON value as canonical text — object keys sorted, 2.0 written as 2 — so
 * two boards compare by content, whatever the key order of the language
 * that built them.
 */
final class Canon
{
    public static function of(mixed $value): string
    {
        if (is_array($value) && ! array_is_list($value)) {
            $value = (object) $value;
        }
        if ($value instanceof stdClass) {
            $map = (array) $value;
            $keys = array_map('strval', array_keys($map));
            sort($keys, SORT_STRING);
            $parts = [];
            foreach ($keys as $key) {
                $parts[] = json_encode($key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).':'.self::of($map[$key]);
            }

            return '{'.implode(',', $parts).'}';
        }
        if (is_array($value)) {
            return '['.implode(',', array_map(self::of(...), $value)).']';
        }
        if (is_float($value) && floor($value) === $value && abs($value) < 9.007199254740992E15) {
            $value = (int) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The recorded reference behaviour (tests/Fixtures/reference/generate.mjs).
     */
    public static function reference(): stdClass
    {
        static $data = null;

        return $data ??= json_decode((string) file_get_contents(__DIR__.'/reference/reference.json'), false, 512, JSON_THROW_ON_ERROR);
    }
}
