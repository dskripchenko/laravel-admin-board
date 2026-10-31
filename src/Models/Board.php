<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A board: its current content (`board`, the snapshot after the last
 * accepted batch), the number of that batch (`seq`) and the log of recent
 * batches for clients that resume.
 *
 * @property int $id
 * @property string $title
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property string $board
 * @property int $seq
 * @property bool $locked
 */
class Board extends Model
{
    protected $table = 'admin_boards';

    protected $fillable = ['title'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'board' => '{"version":1,"objects":{}}',
        'seq' => 0,
        'locked' => false,
    ];

    protected static function booted(): void
    {
        // The foreign key cascades too; this covers connections without
        // foreign key checks (sqlite without them turned on).
        static::deleting(static function (Board $board): void {
            $board->batches()->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'locked' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<BoardBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(BoardBatch::class);
    }
}
