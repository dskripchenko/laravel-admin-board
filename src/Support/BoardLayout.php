<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Support;

use Dskripchenko\LaravelAdmin\Layout\Board as CoreBoard;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Realtime\BoardRealtime;
use Illuminate\Support\Facades\Auth;

/**
 * The board on a screen: the core's `Layout::board()` with the props of the
 * contract — the board's endpoint, the user, the realtime channel, whether
 * they may edit.
 *
 *     public function layout(): array
 *     {
 *         return [BoardLayout::for($board)];
 *     }
 */
final class BoardLayout
{
    /** The core's layout class, once it ships. */
    public static function for(Board $board, ?object $user = null, ?string $title = null, int|string|null $height = null): CoreBoard
    {
        $props = self::props($board, $user, $title, $height);

        $layout = Layout::board()
            ->endpoint($props['endpoint'])
            ->readonly($props['readonly'])
            ->user($props['user'])
            ->realtime($props['realtime'])
            ->pollInterval($props['pollInterval'])
            ->height($props['height']);
        if ($props['title'] !== null && $props['title'] !== '') {
            $layout->title($props['title']);
        }

        return $layout;
    }

    /**
     * The props of the contract: `endpoint`, `readonly`, `user`, `realtime`,
     * `pollInterval`, `title`, `height`.
     *
     * @return array<string, mixed>
     */
    public static function props(Board $board, ?object $user = null, ?string $title = null, int|string|null $height = null): array
    {
        $user ??= self::currentUser();
        $height ??= (int) config('admin-board.height', 640);

        return [
            'endpoint' => self::endpoint($board),
            'readonly' => ! BoardAccess::canEdit($user, $board),
            'user' => BoardUser::of($user),
            'realtime' => app(BoardRealtime::class)->props($board),
            'pollInterval' => max(250, (int) config('admin-board.poll_interval', 1500)),
            'title' => $title ?? $board->title,
            'height' => is_int($height) ? $height.'px' : $height,
        ];
    }

    /**
     * The board's API path relative to the panel's API client.
     */
    public static function endpoint(Board $board): string
    {
        return '/boards/'.$board->getKey();
    }

    private static function currentUser(): ?object
    {
        try {
            return Auth::guard(Panels::currentGuard())->user();
        } catch (\Throwable) {
            return null;
        }
    }
}
