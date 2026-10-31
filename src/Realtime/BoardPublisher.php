<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Realtime;

/**
 * Sends an event to a board's channel. Bound to {@see RealtimePublisher}
 * when dskripchenko/laravel-realtime is installed; tests bind a fake.
 */
interface BoardPublisher
{
    /**
     * @param  string  $channel  the channel name without the `private-` prefix
     */
    public function publish(string $channel, string $event, mixed $data): void;

    /**
     * Registers the authorization callback of a private channel pattern.
     *
     * @param  callable(object, string): bool  $callback  user, board id
     */
    public function authorizeChannel(string $pattern, callable $callback): void;
}
