<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Resources;

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Permissions;
use Dskripchenko\LaravelAdminBoard\Screens\BoardScreen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The boards: a title, how many changes, whether locked; a title opens the
 * board. Creating asks for a title only; the board starts empty.
 */
class BoardResource extends Resource
{
    public static string $model = Board::class;

    public static string $icon = 'presentation';

    public static function slug(): string
    {
        return 'boards';
    }

    public static function permission(): string
    {
        return Permissions::BOARDS;
    }

    public static function label(): string
    {
        return __('Доски');
    }

    public static function singularLabel(): string
    {
        return __('доска');
    }

    public function fields(): array
    {
        return [
            Input::make('title')->title(__('Название'))->required()->rules(['string', 'max:191']),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->label(__('ID'))->sort()->align('right')->width('80px'),
            TableColumn::make('title')->label(__('Название'))->sort()->search()->asLink(BoardScreen::url()),
            TableColumn::make('seq')->label(__('Изменений'))->sort()->align('right'),
            TableColumn::make('locked')->label(__('Заблокирована'))->asBoolean(),
            TableColumn::make('updated_at')->label(__('Изменена'))->sort()->asDateTime(),
        ];
    }

    /**
     * The list without the boards' content, narrowed by the host's scope.
     *
     * @return Builder<Board>
     */
    public function indexQuery(): Builder
    {
        /** @var Builder<Board> $query */
        $query = $this->modelQuery()->select(['id', 'title', 'owner_type', 'owner_id', 'seq', 'locked', 'created_at', 'updated_at']);

        return BoardAccess::scope($query, Auth::guard(Panels::currentGuard())->user());
    }

    public function transformRecord(Model $record): array
    {
        $row = parent::transformRecord($record);
        unset($row['board']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fillModel(Model $model, array $data): void
    {
        $model->forceFill(['title' => (string) ($data['title'] ?? '')]);
    }

    public function recordTitle(Model $row): string
    {
        return (string) $row->getAttribute('title');
    }
}
