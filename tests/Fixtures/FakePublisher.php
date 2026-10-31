<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Fixtures;

use Dskripchenko\LaravelAdminBoard\Realtime\BoardPublisher;

/**
 * Records what would go to laravel-realtime.
 */
final class FakePublisher implements BoardPublisher
{
    /** @var list<array{channel: string, event: string, data: mixed}> */
    public array $published = [];

    /** @var array<string, callable> */
    public array $channels = [];

    public function publish(string $channel, string $event, mixed $data): void
    {
        $this->published[] = ['channel' => $channel, 'event' => $event, 'data' => json_decode((string) json_encode($data), true)];
    }

    public function authorizeChannel(string $pattern, callable $callback): void
    {
        $this->channels[$pattern] = $callback;
    }
}
