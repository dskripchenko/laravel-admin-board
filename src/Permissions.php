<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard;

/**
 * The permission keys of the pack.
 *
 * `view` opens the board list and boards, read-only; `edit` lets a user draw
 * on them (the server refuses batches without it). `create`, `update` and
 * `delete` manage the list itself: new boards, renaming, removing — the keys
 * the core derives from the resource's base key `admin.boards`.
 */
final class Permissions
{
    public const BOARDS = 'admin.boards';

    public const VIEW = 'admin.boards.view';

    public const EDIT = 'admin.boards.edit';

    public const CREATE = 'admin.boards.create';

    public const UPDATE = 'admin.boards.update';

    public const DELETE = 'admin.boards.delete';
}
