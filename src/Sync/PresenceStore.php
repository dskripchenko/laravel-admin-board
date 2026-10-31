<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Sync;

use Dskripchenko\LaravelAdminBoard\Models\Board;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Who is on a board: `touch` of the reference server, kept in the
 * application cache under one key per board — no table. An entry lives
 * `ttl` seconds after the client's last report.
 */
class PresenceStore
{
    public function __construct(private readonly int $ttl = 30, private readonly ?string $store = null) {}

    public static function fromConfig(): self
    {
        $store = config('admin-board.presence.store');

        return new self(max(1, (int) config('admin-board.presence.ttl', 30)), is_string($store) && $store !== '' ? $store : null);
    }

    /**
     * Updates a client's presence; returns everyone seen within the TTL.
     *
     * @param  array<string, mixed>  $presence  with `client`
     * @return list<array<string, mixed>>
     */
    public function touch(Board $board, array $presence): array
    {
        $now = (int) now()->getPreciseTimestamp(3);
        $key = self::key($board);
        $all = $this->cache()->get($key);
        $all = is_array($all) ? $all : [];
        $all[(string) $presence['client']] = [...$presence, 'at' => $now];
        $all = array_filter($all, fn (mixed $p): bool => is_array($p) && (int) ($p['at'] ?? 0) >= $now - $this->ttl * 1000);
        $this->cache()->put($key, $all, $this->ttl);

        return array_values($all);
    }

    /**
     * Everyone seen within the TTL, without reporting.
     *
     * @return list<array<string, mixed>>
     */
    public function all(Board $board): array
    {
        $now = (int) now()->getPreciseTimestamp(3);
        $all = $this->cache()->get(self::key($board));

        return array_values(array_filter(is_array($all) ? $all : [], fn (mixed $p): bool => is_array($p) && (int) ($p['at'] ?? 0) >= $now - $this->ttl * 1000));
    }

    public static function key(Board $board): string
    {
        return 'admin-board:presence:'.$board->getKey();
    }

    private function cache(): Repository
    {
        return Cache::store($this->store);
    }
}
