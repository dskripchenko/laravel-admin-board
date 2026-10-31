<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Concerns;

use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Support\BoardLayout;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A board of a record, for its screens:
 *
 *     class Project extends Model
 *     {
 *         use HasBoard;
 *     }
 *
 *     // a screen's layout()
 *     return [$project->boardLayout()];
 *
 * The board is created on first access, titled by {@see boardTitle()}.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasBoard
{
    /**
     * @return MorphOne<Board, $this>
     */
    public function board(): MorphOne
    {
        return $this->morphOne(Board::class, 'owner');
    }

    /**
     * The record's board, created when it has none. One per record: a
     * unique index on the owner keeps two first visits from making two.
     */
    public function boardModel(): Board
    {
        /** @var Board $board */
        $board = $this->board()->firstOrCreate([], ['title' => $this->boardTitle()]);

        return $board;
    }

    public function boardLayout(): Layout
    {
        return BoardLayout::for($this->boardModel());
    }

    /**
     * The title a new board gets.
     */
    public function boardTitle(): string
    {
        return class_basename($this).' #'.$this->getKey();
    }
}
