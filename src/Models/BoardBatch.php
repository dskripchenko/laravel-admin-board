<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One accepted batch, in the server's order.
 *
 * @property int $id
 * @property int $board_id
 * @property int $seq
 * @property string $client
 * @property int $cseq
 * @property string|null $user
 * @property string $ops
 */
class BoardBatch extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_board_batches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'board_id' => 'integer',
            'seq' => 'integer',
            'cseq' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Board, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * The batch as clients see it: `{ client, cseq, ops, user?, seq }`.
     */
    public function toSeqBatch(): \stdClass
    {
        $out = new \stdClass;
        $out->client = $this->client;
        $out->cseq = $this->cseq;
        $out->ops = json_decode($this->ops, false);
        if ($this->user !== null) {
            $out->user = $this->user;
        }
        $out->seq = $this->seq;

        return $out;
    }
}
