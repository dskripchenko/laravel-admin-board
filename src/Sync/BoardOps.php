<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Sync;

use stdClass;

/**
 * The board operations of `@dskripchenko/board` (`applyOps` of
 * `src/model/ops.ts`, `emptyBoard` of `src/model/parse.ts`) on the server.
 *
 * Shapes, as plain PHP arrays:
 *
 *  - a board is `['version' => 1, 'objects' => [id => object], 'meta' => [...]]`,
 *    `meta` absent when the board has none;
 *  - an object or `meta` is a map of property => JSON value;
 *  - an operation is `['k' => 'create'|'set'|'delete'|'meta', 'id' => string]`
 *    with `obj` (create) or `props` (set, meta), both maps.
 *
 * Property values stay as `json_decode(..., false)` gives them (`stdClass`
 * for JSON objects), so `{}` and `[]` survive a round trip unchanged.
 * {@see self::export()} turns a board back into a JSON-ready structure.
 */
final class BoardOps
{
    public const META = '$meta';

    public const KINDS = ['create', 'set', 'delete', 'meta'];

    /** Properties a `set` never changes. */
    private const IMMUTABLE = ['id', 'type'];

    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return ['version' => 1, 'objects' => []];
    }

    /**
     * Applies operations and returns the new board and the operations that
     * undo them, in the order to apply them. Operations on missing objects
     * are skipped, so replays are harmless.
     *
     * @param  array<string, mixed>  $board
     * @param  list<array<string, mixed>>  $ops
     * @return array{board: array<string, mixed>, inverse: list<array<string, mixed>>}
     */
    public static function apply(array $board, array $ops): array
    {
        /** @var array<array-key, array<array-key, mixed>> $objects */
        $objects = (array) ($board['objects'] ?? []);
        // null stands for "no meta" (undefined in the reference).
        $meta = array_key_exists('meta', $board) && is_array($board['meta']) ? $board['meta'] : null;
        $inverse = [];

        foreach ($ops as $op) {
            $kind = $op['k'];
            if ($kind === 'meta') {
                $before = [];
                $next = $meta ?? [];
                $changed = false;
                foreach ((array) ($op['props'] ?? []) as $key => $value) {
                    $has = array_key_exists($key, $next);
                    if (self::same($has, $has ? $next[$key] : null, $value !== null, $value)) {
                        continue;
                    }
                    $before[$key] = $has ? $next[$key] : null;
                    if ($value === null) {
                        unset($next[$key]);
                    } else {
                        $next[$key] = $value;
                    }
                    $changed = true;
                }
                if (! $changed) {
                    continue;
                }
                $meta = $next === [] ? null : $next;
                $inverse[] = ['k' => 'meta', 'id' => self::META, 'props' => $before];

                continue;
            }

            $id = $op['id'];
            $exists = array_key_exists($id, $objects);
            if ($kind === 'create') {
                if ($exists) {
                    continue;
                }
                $obj = (array) ($op['obj'] ?? []);
                $obj['id'] = $id;
                $objects[$id] = $obj;
                $inverse[] = ['k' => 'delete', 'id' => $id];
            } elseif ($kind === 'delete') {
                if (! $exists) {
                    continue;
                }
                $current = $objects[$id];
                unset($objects[$id]);
                $inverse[] = ['k' => 'create', 'id' => $id, 'obj' => $current];
            } else {
                if (! $exists) {
                    continue;
                }
                $current = $objects[$id];
                $next = $current;
                $before = [];
                $changed = false;
                foreach ((array) ($op['props'] ?? []) as $key => $value) {
                    if (in_array((string) $key, self::IMMUTABLE, true)) {
                        continue;
                    }
                    $has = array_key_exists($key, $current);
                    if (self::same($has, $has ? $current[$key] : null, $value !== null, $value)) {
                        continue;
                    }
                    $before[$key] = $has ? $current[$key] : null;
                    if ($value === null) {
                        unset($next[$key]);
                    } else {
                        $next[$key] = $value;
                    }
                    $changed = true;
                }
                if (! $changed) {
                    continue;
                }
                $objects[$id] = $next;
                $inverse[] = ['k' => 'set', 'id' => $id, 'props' => $before];
            }
        }

        $out = $board;
        $out['objects'] = $objects;
        if ($meta !== null) {
            $out['meta'] = $meta;
        } else {
            unset($out['meta']);
        }

        return ['board' => $out, 'inverse' => array_reverse($inverse)];
    }

    /**
     * Whether a board is locked for editing.
     *
     * @param  array<string, mixed>  $board
     */
    public static function isLocked(array $board): bool
    {
        return is_array($board['meta'] ?? null) && ($board['meta']['locked'] ?? null) === true;
    }

    /**
     * Reads a stored board (JSON text). Anything that is not a board object
     * gives the empty board.
     *
     * @return array<string, mixed>
     */
    public static function decode(?string $json): array
    {
        $data = $json === null || $json === '' ? null : json_decode($json, false);
        if (! $data instanceof stdClass) {
            return self::empty();
        }
        $board = (array) $data;
        $objects = [];
        if (($board['objects'] ?? null) instanceof stdClass) {
            foreach ((array) $board['objects'] as $id => $obj) {
                if ($obj instanceof stdClass) {
                    $objects[$id] = (array) $obj;
                }
            }
        }
        $board['objects'] = $objects;
        if (array_key_exists('meta', $board)) {
            if ($board['meta'] instanceof stdClass) {
                $board['meta'] = (array) $board['meta'];
            } else {
                unset($board['meta']);
            }
        }

        return $board;
    }

    /**
     * The board as a JSON-ready structure: maps become objects, even empty
     * ones or ones keyed by numbers.
     *
     * @param  array<string, mixed>  $board
     */
    public static function export(array $board): stdClass
    {
        $objects = new stdClass;
        foreach ((array) ($board['objects'] ?? []) as $id => $obj) {
            $objects->{(string) $id} = self::object((array) $obj);
        }
        $out = self::object($board);
        $out->objects = $objects;
        if (isset($board['meta']) && is_array($board['meta'])) {
            $out->meta = self::object($board['meta']);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $board
     */
    public static function encode(array $board): string
    {
        return (string) json_encode(self::export($board), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * An operation list as decoded from JSON (objects as stdClass) checked
     * and turned into operation arrays. Null when anything is malformed.
     *
     * @return list<array<string, mixed>>|null
     */
    public static function normalize(mixed $ops): ?array
    {
        if (! is_array($ops) || ! array_is_list($ops)) {
            return null;
        }
        $out = [];
        foreach ($ops as $op) {
            if (! $op instanceof stdClass || ! is_string($op->k ?? null) || ! in_array($op->k, self::KINDS, true) || ! is_string($op->id ?? null)) {
                return null;
            }
            $item = ['k' => $op->k, 'id' => $op->id];
            if ($op->k === 'create') {
                if (! ($op->obj ?? null) instanceof stdClass) {
                    return null;
                }
                $item['obj'] = (array) $op->obj;
            } elseif ($op->k !== 'delete') {
                if (! ($op->props ?? null) instanceof stdClass) {
                    return null;
                }
                $item['props'] = (array) $op->props;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Operation arrays as JSON-ready structures.
     *
     * @param  list<array<string, mixed>>  $ops
     * @return list<stdClass>
     */
    public static function exportOps(array $ops): array
    {
        return array_map(static function (array $op): stdClass {
            $out = new stdClass;
            foreach ($op as $key => $value) {
                $out->{$key} = in_array($key, ['obj', 'props'], true) ? self::object((array) $value) : $value;
            }

            return $out;
        }, $ops);
    }

    /**
     * `sameValue` of the reference, with "absent" spelled out: equal when
     * both are absent, or identical, or (for objects and arrays) equal as
     * JSON. Numbers compare by value, as in JavaScript.
     */
    private static function same(bool $hasA, mixed $a, bool $hasB, mixed $b): bool
    {
        if (! $hasA || ! $hasB) {
            return $hasA === $hasB;
        }
        if ($a === $b) {
            return true;
        }
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a == $b;
        }
        $structured = static fn (mixed $v): bool => is_array($v) || $v instanceof stdClass;
        if (! $structured($a) || ! $structured($b)) {
            return false;
        }

        return self::json($a) === self::json($b);
    }

    private static function json(mixed $value): string
    {
        return (string) json_encode(self::integral($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * JavaScript has one number type: 2.0 and 2 print alike.
     */
    private static function integral(mixed $value): mixed
    {
        if (is_float($value) && floor($value) === $value && abs($value) < 9.007199254740992E15) {
            return (int) $value;
        }
        if (is_array($value)) {
            return array_map(self::integral(...), $value);
        }
        if ($value instanceof stdClass) {
            $out = new stdClass;
            foreach ((array) $value as $k => $v) {
                $out->{(string) $k} = self::integral($v);
            }

            return $out;
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $map
     */
    private static function object(array $map): stdClass
    {
        $out = new stdClass;
        foreach ($map as $key => $value) {
            $out->{(string) $key} = $value;
        }

        return $out;
    }
}
