<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Feature;

use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Sync\BatchRejected;
use Dskripchenko\LaravelAdminBoard\Sync\BoardServer;
use Dskripchenko\LaravelAdminBoard\Tests\Fixtures\Canon;
use Dskripchenko\LaravelAdminBoard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The database server against the reference `BoardServer`: for recorded
 * random batches from several clients — resends, skipped numbers, batches
 * over the limits, locking and unlocking, a short log — the same numbers,
 * the same refusals, the same operations kept, the same board and the same
 * answers to "batches after N".
 */
final class ServerReferenceTest extends TestCase
{
    /**
     * @return iterable<string, array{object}>
     */
    public static function cases(): iterable
    {
        foreach (Canon::reference()->server as $case) {
            yield sprintf('seed %d (ops %d, objects %d, keep %d)', $case->seed, $case->limits->maxOps, $case->limits->maxObjects, $case->keep) => [$case];
        }
    }

    #[DataProvider('cases')]
    public function test_the_server_matches_the_reference(object $case): void
    {
        $server = new BoardServer($case->limits->maxOps, $case->limits->maxObjects, $case->keep, 100_000);
        $board = Board::query()->create(['title' => 'Reference']);

        foreach ($case->steps as $i => $step) {
            $batch = $step->batch;
            $expected = $step->expected;
            try {
                $received = $server->receive($board, $batch->client, $batch->cseq, $batch->ops);
            } catch (BatchRejected $e) {
                $this->assertObjectHasProperty('error', $expected, "step {$i}: refused, the reference accepted it: ".$e->getMessage());

                continue;
            }
            $this->assertObjectNotHasProperty('error', $expected, "step {$i}: accepted, the reference refused it");
            $this->assertSame([$expected->seq, $expected->fresh], [$received->seq, $received->fresh], "step {$i}");
            if ($received->fresh) {
                $kept = isset($expected->kept) ? array_map(static fn (int $k): mixed => $batch->ops[$k], $expected->kept) : $batch->ops;
                $this->assertSame(Canon::of($kept), Canon::of($received->batch?->ops), "step {$i}: the operations kept");
            }
        }

        $snapshot = $server->snapshot($board);
        $this->assertSame($case->expected->snapshot->seq, $snapshot['seq']);
        $this->assertSame(Canon::of($case->expected->snapshot->board), Canon::of($snapshot['board']));

        foreach ($case->expected->since as $since) {
            $batches = $server->since($board, $since->after);
            $this->assertSame($since->seqs, $batches === null ? null : array_map(static fn (object $b): int => $b->seq, $batches), "since {$since->after}");
        }
    }
}
