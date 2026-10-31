<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Widgets;

use Dskripchenko\LaravelAdmin\Widget\Widget;
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Permissions;
use Dskripchenko\LaravelAdminBoard\Support\BoardLayout;
use Illuminate\Support\Facades\Auth;

/**
 * A board on a dashboard: widget type `board`, its data the props of the
 * board layout. Which board: `admin-board.widget.board`, or override
 * {@see Board()} in a subclass and register that one.
 *
 * Until the core ships its own `Widget\BoardWidget`, this one is the
 * server side of the widget; the type and props are the contract's.
 */
class BoardWidget extends Widget
{
    protected int $size = 12;

    protected ?int $rowSpan = 4;

    protected array|string|null $permission = Permissions::VIEW;

    public function __construct()
    {
        $this->title((string) config('admin-board.widget.title', 'Доска'));
    }

    public static function slug(): string
    {
        return 'admin.board';
    }

    public function widgetType(): string
    {
        return 'board';
    }

    /**
     * The board to show; null — none (the widget says so).
     */
    public function board(): ?Board
    {
        $id = config('admin-board.widget.board');

        return is_numeric($id) ? Board::query()->find((int) $id, ['id', 'title', 'owner_type', 'owner_id', 'seq', 'locked']) : null;
    }

    public function data(): array
    {
        $board = $this->board();
        $user = Auth::guard(\Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard())->user();
        if ($board === null || ! BoardAccess::canView($user, $board)) {
            return ['missing' => true];
        }

        return BoardLayout::props($board, $user, title: '', height: '100%');
    }
}
