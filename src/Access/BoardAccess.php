<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Access;

use Closure;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Permissions;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may see and draw on a board.
 *
 * The permission comes first: `admin.boards.view` to see, plus
 * `admin.boards.edit` to draw. On top of it a host can narrow it per board —
 * a board owned by a record visible only to whoever may view the record:
 *
 *     BoardAccess::authorizeUsing(function (object $user, Board $board, string $ability): ?bool {
 *         $owner = $board->owner;
 *         return $owner === null ? null : Gate::forUser($user)->allows($ability === 'edit' ? 'update' : 'view', $owner);
 *     });
 *
 *     BoardAccess::scopeUsing(fn (Builder $boards, object $user) => $boards->whereNull('owner_type'));
 *
 * The callback returns false to refuse; true or null keep the permission's
 * answer. It also guards the realtime channel, where there is no request:
 * it must use the user it is given, not `auth()`.
 */
final class BoardAccess
{
    /** @var (Closure(object, Board, string): ?bool)|null */
    private static ?Closure $authorize = null;

    /** @var (Closure(Builder<Board>, object): mixed)|null */
    private static ?Closure $scope = null;

    /**
     * @param  (Closure(object, Board, string): ?bool)|null  $callback
     */
    public static function authorizeUsing(?Closure $callback): void
    {
        self::$authorize = $callback;
    }

    /**
     * Narrows the board list (the resource) to what a user may see.
     *
     * @param  (Closure(Builder<Board>, object): mixed)|null  $callback
     */
    public static function scopeUsing(?Closure $callback): void
    {
        self::$scope = $callback;
    }

    public static function canView(?object $user, Board $board): bool
    {
        return self::allows($user, $board, 'view', [Permissions::VIEW]);
    }

    public static function canEdit(?object $user, Board $board): bool
    {
        return self::allows($user, $board, 'edit', [Permissions::VIEW, Permissions::EDIT]);
    }

    /**
     * @param  Builder<Board>  $query
     * @return Builder<Board>
     */
    public static function scope(Builder $query, ?object $user): Builder
    {
        if (self::$scope !== null && $user !== null) {
            (self::$scope)($query, $user);
        }

        return $query;
    }

    /**
     * @param  list<string>  $permissions
     */
    private static function allows(?object $user, Board $board, string $ability, array $permissions): bool
    {
        if ($user === null || ! PermissionCheck::allows($permissions, $user)) {
            return false;
        }

        return self::$authorize === null || (self::$authorize)($user, $board, $ability) !== false;
    }
}
