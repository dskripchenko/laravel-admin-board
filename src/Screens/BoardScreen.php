<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Screens;

use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Permissions;
use Dskripchenko\LaravelAdminBoard\Support\BoardLayout;
use Illuminate\Support\Facades\Auth;

/**
 * A board on its own page, `/screens/board?id=<id>`: read-only for a user
 * without `admin.boards.edit`.
 */
class BoardScreen extends Screen
{
    private ?Board $board = null;

    private bool $loaded = false;

    public static function slug(): string
    {
        return 'board';
    }

    /**
     * The page of a board in the current panel.
     */
    public static function url(int|string $id = '{id}'): string
    {
        $path = trim(Panels::current()->path, '/');

        return ($path === '' ? '' : '/'.$path).'/screens/board?id='.$id;
    }

    public function name(): string
    {
        $board = $this->board();

        return $board === null ? __('Доска') : $board->title;
    }

    public function permission(): string
    {
        return Permissions::VIEW;
    }

    public function query(mixed ...$params): array
    {
        $board = $this->board();

        return ['found' => $board !== null, 'id' => $board?->id, 'title' => $board?->title];
    }

    public function layout(): array
    {
        $board = $this->board();
        if ($board === null) {
            return [Layout::markdown(__('Доска не найдена. Откройте её из списка досок.'))];
        }

        return [BoardLayout::for($board, title: '')];
    }

    private function board(): ?Board
    {
        if ($this->loaded) {
            return $this->board;
        }
        $this->loaded = true;
        $id = self::requestedId();
        $board = $id === null ? null : Board::query()->find($id, ['id', 'title', 'owner_type', 'owner_id', 'seq', 'locked']);
        $user = Auth::guard(Panels::currentGuard())->user();

        return $this->board = $board !== null && BoardAccess::canView($user, $board) ? $board : null;
    }

    private static function requestedId(): ?int
    {
        $request = request();
        foreach ([$request->query('id'), $request->input('payload.id'), $request->input('parameters.0.id')] as $candidate) {
            if ((is_string($candidate) || is_int($candidate)) && ctype_digit((string) $candidate)) {
                return (int) $candidate;
            }
        }

        return null;
    }
}
