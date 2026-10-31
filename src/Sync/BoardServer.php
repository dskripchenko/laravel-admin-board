<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Sync;

use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Models\BoardBatch;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * `BoardServer` of `@dskripchenko/board` (`src/sync/server.ts`) over the
 * database: orders batches, ignores resends, keeps the limits, drops object
 * changes on a locked board, serves the snapshot and the batches after a
 * number.
 *
 * A batch is received in a transaction holding the board's row lock, so
 * batches of one board get consecutive numbers whatever the concurrency.
 *
 * One difference from the reference: it remembers a client's last batch
 * (for spotting resends) as long as the log keeps one of that client's
 * batches; the reference keeps that in memory for good.
 */
class BoardServer
{
    public function __construct(
        private readonly int $maxOps = 500,
        private readonly int $maxObjects = 10_000,
        private readonly int $keep = 10_000,
        private readonly int $pollLimit = 1_000,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            maxOps: (int) config('admin-board.limits.max_ops', 500),
            maxObjects: (int) config('admin-board.limits.max_objects', 10_000),
            keep: max(1, (int) config('admin-board.keep', 10_000)),
            pollLimit: max(1, (int) config('admin-board.poll_limit', 1_000)),
        );
    }

    /**
     * The board as it is now and the number of the last batch in it.
     *
     * @return array{board: stdClass, seq: int}
     */
    public function snapshot(Board $board): array
    {
        $fresh = Board::query()->findOrFail($board->getKey(), ['id', 'board', 'seq']);

        return ['board' => BoardOps::export(BoardOps::decode($fresh->board)), 'seq' => $fresh->seq];
    }

    /**
     * Accepts a batch and returns its number; a resend gets the number it
     * got before and is not applied again.
     *
     * @param  mixed  $ops  the operations as decoded from JSON (objects as stdClass)
     *
     * @throws BatchRejected a malformed batch, or one over the limits
     */
    public function receive(Board $board, string $client, int $cseq, mixed $ops, ?string $user = null): Received
    {
        $normalized = BoardOps::normalize($ops);
        if ($client === '' || strlen($client) > 64 || $cseq < 0 || $normalized === null || ! is_array($ops)) {
            throw new BatchRejected('A batch is { client, cseq, ops } with known operations');
        }

        return DB::transaction(function () use ($board, $client, $cseq, $ops, $normalized, $user): Received {
            /** @var Board $row */
            $row = Board::query()->lockForUpdate()->findOrFail($board->getKey());

            $last = BoardBatch::query()->where('board_id', $row->id)->where('client', $client)
                ->orderByDesc('cseq')->first(['seq', 'cseq']);
            if ($last !== null && $cseq <= $last->cseq) {
                $old = $cseq === $last->cseq ? $last : BoardBatch::query()
                    ->where('board_id', $row->id)->where('client', $client)->where('cseq', $cseq)->first(['seq']);

                return new Received($old === null ? $last->seq : $old->seq, false);
            }

            if (count($normalized) > $this->maxOps) {
                throw new BatchRejected("A batch has more than {$this->maxOps} operations");
            }
            $content = BoardOps::decode($row->board);
            $creates = count(array_filter($normalized, static fn (array $op): bool => $op['k'] === 'create'));
            if ($creates > 0 && count($content['objects']) + $creates > $this->maxObjects) {
                throw new BatchRejected("A board holds at most {$this->maxObjects} objects");
            }

            // A locked board takes only changes of its settings (unlocking among
            // them): object changes are dropped here, so every client rolls them
            // back alike.
            if (BoardOps::isLocked($content)) {
                $keepIndexes = array_keys(array_filter($normalized, static fn (array $op): bool => $op['k'] === 'meta'));
                $normalized = array_values(array_intersect_key($normalized, array_flip($keepIndexes)));
                $ops = array_values(array_intersect_key($ops, array_flip($keepIndexes)));
            }

            $seq = $row->seq + 1;
            $content = BoardOps::apply($content, $normalized)['board'];
            $json = (string) json_encode($ops, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

            $batch = new BoardBatch;
            $batch->forceFill([
                'board_id' => $row->id,
                'seq' => $seq,
                'client' => $client,
                'cseq' => $cseq,
                'user' => $user,
                'ops' => $json,
            ])->save();

            $row->forceFill([
                'board' => BoardOps::encode($content),
                'seq' => $seq,
                'locked' => BoardOps::isLocked($content),
            ])->save();

            BoardBatch::query()->where('board_id', $row->id)->where('seq', '<=', $seq - $this->keep)->delete();

            return new Received($seq, true, $batch->toSeqBatch());
        }, 3);
    }

    /**
     * Batches after `$after` (at most `poll_limit` of them), or null when
     * they are no longer in the log: the client must reload the snapshot.
     *
     * @return list<stdClass>|null
     */
    public function since(Board $board, int $after): ?array
    {
        $seq = (int) Board::query()->whereKey($board->getKey())->value('seq');
        if ($after >= $seq) {
            return [];
        }
        $first = BoardBatch::query()->where('board_id', $board->getKey())->min('seq');
        if ($first === null || (int) $first > $after + 1) {
            return null;
        }

        return BoardBatch::query()->where('board_id', $board->getKey())->where('seq', '>', $after)
            ->orderBy('seq')->limit($this->pollLimit)->get()
            ->map(static fn (BoardBatch $b): stdClass => $b->toSeqBatch())->values()->all();
    }
}
