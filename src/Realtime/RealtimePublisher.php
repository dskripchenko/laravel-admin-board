<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Realtime;

/**
 * The publisher over dskripchenko/laravel-realtime. The package is optional,
 * so its manager is resolved by name.
 */
final class RealtimePublisher implements BoardPublisher
{
    public const MANAGER = 'Dskripchenko\\Realtime\\RealtimeManager';

    public static function available(): bool
    {
        return class_exists(self::MANAGER);
    }

    public function publish(string $channel, string $event, mixed $data): void
    {
        app(self::MANAGER)->publish('private-'.$channel, $event, $data);
    }

    public function authorizeChannel(string $pattern, callable $callback): void
    {
        app(self::MANAGER)->channel($pattern, $callback);
    }
}
