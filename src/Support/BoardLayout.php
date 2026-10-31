<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Support;

use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Realtime\BoardRealtime;
use Illuminate\Support\Facades\Auth;
use ReflectionMethod;

/**
 * The board on a screen: the core's `Layout::board()` with the props of the
 * contract, or — on a core without it — {@see FallbackBoardLayout} with the
 * same type and props.
 *
 *     public function layout(): array
 *     {
 *         return [BoardLayout::for($board)];
 *     }
 */
final class BoardLayout
{
    /** The core's layout class, once it ships. */
    public const CORE_LAYOUT = 'Dskripchenko\\LaravelAdmin\\Layout\\Board';

    public static function for(Board $board, ?object $user = null, ?string $title = null, int|string|null $height = null): Layout
    {
        $props = self::props($board, $user, $title, $height);

        return self::coreLayout($props) ?? new FallbackBoardLayout($props);
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

    /**
     * @param  array<string, mixed>  $props
     */
    private static function coreLayout(array $props): ?Layout
    {
        if (! class_exists(self::CORE_LAYOUT) || ! method_exists(Layout::class, 'board')) {
            return null;
        }
        $factory = new ReflectionMethod(Layout::class, 'board');
        $layout = match ($factory->getNumberOfRequiredParameters()) {
            0 => $factory->invoke(null),
            1 => $factory->invoke(null, $props['endpoint']),
            default => null,
        };
        if (! $layout instanceof Layout) {
            return null;
        }
        foreach (['endpoint', 'readonly', 'user', 'realtime', 'pollInterval', 'title', 'height'] as $setter) {
            if (method_exists($layout, $setter) && ($props[$setter] !== null || $setter === 'realtime')) {
                $layout->{$setter}($props[$setter]);
            }
        }

        return $layout;
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
