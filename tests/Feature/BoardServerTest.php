<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Feature;

use Dskripchenko\LaravelAdminBoard\Models\BoardBatch;
use Dskripchenko\LaravelAdminBoard\Sync\BatchRejected;
use Dskripchenko\LaravelAdminBoard\Sync\BoardOps;
use Dskripchenko\LaravelAdminBoard\Sync\BoardServer;
use Dskripchenko\LaravelAdminBoard\Tests\TestCase;

final class BoardServerTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $ops
     * @return list<\stdClass>
     */
    private static function ops(array $ops): array
    {
        return json_decode((string) json_encode($ops), false);
    }

    public function test_batches_get_consecutive_numbers_and_change_the_board(): void
    {
        $server = new BoardServer;
        $board = $this->makeBoard();

        $this->assertSame(1, $server->receive($board, 'a', 1, self::ops([['k' => 'create', 'id' => 's1', 'obj' => self::sticky()]]))->seq);
        $this->assertSame(2, $server->receive($board, 'b', 1, self::ops([['k' => 'set', 'id' => 's1', 'props' => ['text' => 'B', 'x' => 10]]]))->seq);
        $this->assertSame(3, $server->receive($board, 'a', 2, self::ops([['k' => 'set', 'id' => 's1', 'props' => ['text' => 'A']]]))->seq);

        $snapshot = $server->snapshot($board);
        $this->assertSame(3, $snapshot['seq']);
        $this->assertSame('A', $snapshot['board']->objects->s1->text, 'the last write in the server order wins');
        $this->assertSame(10, $snapshot['board']->objects->s1->x);
        $this->assertSame(3, $board->refresh()->seq);
    }

    public function test_delete_wins_over_a_later_set(): void
    {
        $server = new BoardServer;
        $board = $this->makeBoard();
        $server->receive($board, 'a', 1, self::ops([['k' => 'create', 'id' => 's1', 'obj' => self::sticky()]]));
        $server->receive($board, 'a', 2, self::ops([['k' => 'delete', 'id' => 's1']]));
        $server->receive($board, 'b', 1, self::ops([['k' => 'set', 'id' => 's1', 'props' => ['text' => 'late']]]));

        $this->assertEquals(new \stdClass, $server->snapshot($board)['board']->objects);
    }

    public function test_a_resend_gets_the_same_number_and_is_not_applied_again(): void
    {
        $server = new BoardServer;
        $board = $this->makeBoard();
        $ops = self::ops([['k' => 'create', 'id' => 's1', 'obj' => self::sticky()]]);

        $first = $server->receive($board, 'a', 1, $ops);
        $server->receive($board, 'b', 1, self::ops([['k' => 'delete', 'id' => 's1']]));
        $again = $server->receive($board, 'a', 1, $ops);

        $this->assertTrue($first->fresh);
        $this->assertFalse($again->fresh);
        $this->assertSame(1, $again->seq);
        $this->assertSame(2, $board->refresh()->seq);
        $this->assertSame(2, BoardBatch::query()->count());
        $this->assertEquals(new \stdClass, $server->snapshot($board)['board']->objects, 'the resend did not create the sticky again');
    }

    public function test_an_older_number_never_received_counts_as_a_resend(): void
    {
        $server = new BoardServer;
        $board = $this->makeBoard();
        $server->receive($board, 'a', 5, self::ops([]));

        $older = $server->receive($board, 'a', 3, self::ops([['k' => 'create', 'id' => 's1', 'obj' => self::sticky()]]));

        $this->assertFalse($older->fresh);
        $this->assertSame(1, $older->seq, 'the number of the client\'s last batch, as the reference answers');
    }

    public function test_a_batch_over_the_operation_limit_is_refused(): void
    {
        $board = $this->makeBoard();
        $ops = self::ops(array_fill(0, 501, ['k' => 'delete', 'id' => 'x']));

        $this->expectException(BatchRejected::class);
        $this->expectExceptionMessage('more than 500 operations');
        (new BoardServer)->receive($board, 'a', 1, $ops);
    }

    public function test_a_board_holds_at_most_the_object_limit(): void
    {
        $server = new BoardServer(maxObjects: 3);
        $board = $this->makeBoard();
        $create = static fn (string $id): array => ['k' => 'create', 'id' => $id, 'obj' => self::sticky()];
        $server->receive($board, 'a', 1, self::ops([$create('1'), $create('2')]));

        try {
            $server->receive($board, 'a', 2, self::ops([$create('3'), $create('4')]));
            $this->fail('over the limit');
        } catch (BatchRejected $e) {
            $this->assertStringContainsString('at most 3 objects', $e->getMessage());
        }
        $this->assertSame(1, $board->refresh()->seq, 'a refused batch takes no number');
        $this->assertSame(2, $server->receive($board, 'a', 2, self::ops([$create('3')]))->seq, 'the refused cseq can be used again');
    }

    public function test_a_locked_board_keeps_only_meta_operations(): void
    {
        $server = new BoardServer;
        $board = $this->makeBoard();
        $server->receive($board, 'a', 1, self::ops([
            ['k' => 'create', 'id' => 's1', 'obj' => self::sticky()],
            ['k' => 'meta', 'id' => BoardOps::META, 'props' => ['locked' => true, 'lockedBy' => 'Olga']],
        ]));
        $this->assertTrue($board->refresh()->locked);

        $received = $server->receive($board, 'b', 1, self::ops([
            ['k' => 'set', 'id' => 's1', 'props' => ['text' => 'nope']],
            ['k' => 'meta', 'id' => BoardOps::META, 'props' => ['title' => 'Locked plan']],
        ]));

        $this->assertTrue($received->fresh);
        $this->assertSame(2, $received->seq);
        $this->assertCount(1, $received->batch?->ops ?? []);
        $this->assertSame('meta', $received->batch?->ops[0]->k);
        $snapshot = $server->snapshot($board);
        $this->assertSame('Hi', $snapshot['board']->objects->s1->text);
        $this->assertSame('Locked plan', $snapshot['board']->meta->title);

        $server->receive($board, 'b', 2, self::ops([['k' => 'meta', 'id' => BoardOps::META, 'props' => ['locked' => null, 'lockedBy' => null]]]));
        $server->receive($board, 'b', 3, self::ops([['k' => 'set', 'id' => 's1', 'props' => ['text' => 'yes']]]));
        $this->assertFalse($board->refresh()->locked);
        $this->assertSame('yes', $server->snapshot($board)['board']->objects->s1->text);
    }

    public function test_since_returns_the_batches_after_a_number_or_null_past_the_log(): void
    {
        $server = new BoardServer(keep: 3);
        $board = $this->makeBoard();
        for ($i = 1; $i <= 5; $i++) {
            $server->receive($board, 'a', $i, self::ops([['k' => 'meta', 'id' => BoardOps::META, 'props' => ['title' => "v{$i}"]]]));
        }

        $this->assertSame(3, BoardBatch::query()->count(), 'the log is trimmed to keep');
        $this->assertSame([4, 5], array_map(static fn ($b) => $b->seq, $server->since($board, 3) ?? []));
        $this->assertSame([3, 4, 5], array_map(static fn ($b) => $b->seq, $server->since($board, 2) ?? []));
        $this->assertNull($server->since($board, 1), 'batch 2 is no longer kept');
        $this->assertSame([], $server->since($board, 5));
        $this->assertSame([], $server->since($board, 9));

        $batch = ($server->since($board, 4) ?? [])[0];
        $this->assertSame(['client', 'cseq', 'ops', 'seq'], array_keys((array) $batch));
        $this->assertSame('v5', $batch->ops[0]->props->title);
    }

    public function test_a_poll_answer_is_capped(): void
    {
        $server = new BoardServer(pollLimit: 2);
        $board = $this->makeBoard();
        for ($i = 1; $i <= 5; $i++) {
            $server->receive($board, 'a', $i, self::ops([]));
        }

        $this->assertSame([1, 2], array_map(static fn ($b) => $b->seq, $server->since($board, 0) ?? []));
        $this->assertSame([3, 4], array_map(static fn ($b) => $b->seq, $server->since($board, 2) ?? []));
    }

    public function test_the_operations_come_back_as_they_were_sent(): void
    {
        $server = new BoardServer;
        $board = $this->makeBoard();
        $json = '[{"k":"create","id":"7","obj":{"type":"ink","points":[0,0.5,1,2],"x":1.5,"from":{},"tags":[]}}]';
        $server->receive($board, 'a', 1, json_decode($json, false));

        $this->assertSame($json, json_encode(($server->since($board, 0) ?? [])[0]->ops));
        $this->assertSame('{"type":"ink","points":[0,0.5,1,2],"x":1.5,"from":{},"tags":[],"id":"7"}', json_encode($server->snapshot($board)['board']->objects->{'7'}));
    }
}
