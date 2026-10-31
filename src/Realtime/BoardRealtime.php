<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Realtime;

use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * The optional realtime side: after a fresh batch the `batch` event, after
 * a presence report the `presence` event (the whole list), both on the
 * private channel `admin-board.{id}`, authorized by the same view check as
 * the HTTP endpoints.
 *
 * `admin-board.realtime.enabled`: null — on when a publisher is bound (the
 * provider binds one when dskripchenko/laravel-realtime is installed); false
 * — off; true — on if a publisher is bound.
 */
final class BoardRealtime
{
    public const CHANNEL = 'admin-board';

    public function __construct(private readonly Container $app) {}

    public function enabled(): bool
    {
        $flag = config('admin-board.realtime.enabled');
        if ($flag === false) {
            return false;
        }

        return $this->app->bound(BoardPublisher::class);
    }

    public static function channel(Board $board): string
    {
        return self::CHANNEL.'.'.$board->getKey();
    }

    /**
     * `{ ticketUrl, channel }` for the layout, or null when realtime is off.
     *
     * @return array{ticketUrl: string, channel: string}|null
     */
    public function props(Board $board): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        return ['ticketUrl' => $this->ticketUrl(), 'channel' => self::channel($board)];
    }

    public function ticketUrl(): string
    {
        $configured = config('admin-board.realtime.ticket_url');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return Route::has('realtime.ticket') ? route('realtime.ticket', [], false) : '/realtime/ticket';
    }

    public function batch(Board $board, mixed $batch): void
    {
        $this->send($board, 'batch', $batch);
    }

    /**
     * @param  list<array<string, mixed>>  $presence
     */
    public function presence(Board $board, array $presence): void
    {
        $this->send($board, 'presence', $presence);
    }

    /**
     * Registers the channel's authorization: whoever may view the board.
     */
    public function registerChannel(): void
    {
        if (! $this->enabled()) {
            return;
        }
        $this->publisher()->authorizeChannel(self::CHANNEL.'.{id}', static function (object $user, string $id): bool {
            $board = ctype_digit($id) ? Board::query()->find((int) $id) : null;

            return $board !== null && BoardAccess::canView($user, $board);
        });
    }

    private function send(Board $board, string $event, mixed $data): void
    {
        if (! $this->enabled()) {
            return;
        }
        try {
            $this->publisher()->publish(self::channel($board), $event, $data);
        } catch (Throwable $e) {
            // The change is stored; clients catch up through poll. A broker
            // outage must not fail the request that made the change.
            report($e);
        }
    }

    private function publisher(): BoardPublisher
    {
        return $this->app->make(BoardPublisher::class);
    }
}
