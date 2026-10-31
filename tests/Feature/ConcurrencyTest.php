<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Feature;

use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Models\BoardBatch;
use Dskripchenko\LaravelAdminBoard\Sync\BoardServer;
use Dskripchenko\LaravelAdminBoard\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class ConcurrencyTest extends TestCase
{
    /**
     * @return list<\stdClass>
     */
    private static function create(string $id): array
    {
        return json_decode((string) json_encode([['k' => 'create', 'id' => $id, 'obj' => self::sticky()]]), false);
    }

    public function test_interleaved_receives_from_two_clients_get_unique_consecutive_numbers(): void
    {
        $board = $this->makeBoard();
        $server = new BoardServer;
        $seqs = [];
        // Two writers whose requests alternate: each receive sees the other's
        // batch, as it would after waiting for the row lock.
        for ($i = 1; $i <= 25; $i++) {
            $seqs[] = $server->receive(Board::query()->findOrFail($board->id), 'a', $i, self::create("a{$i}"))->seq;
            $seqs[] = $server->receive($board, 'b', $i, self::create("b{$i}"))->seq;
        }

        $this->assertSame(range(1, 50), $seqs);
        $this->assertSame(range(1, 50), BoardBatch::query()->orderBy('seq')->pluck('seq')->all());
        $this->assertCount(50, (array) $server->snapshot($board)['board']->objects);
    }

    public function test_the_board_row_is_read_inside_the_transaction_before_the_batch_is_written(): void
    {
        $board = $this->makeBoard();
        $log = [];
        // RefreshDatabase runs each test in a transaction of its own.
        $base = DB::transactionLevel();
        DB::listen(function (QueryExecuted $q) use (&$log): void {
            $log[] = [strtolower(strtok($q->sql, ' ')), DB::transactionLevel(), $q->sql];
        });

        (new BoardServer)->receive($board, 'a', 1, self::create('s1'));

        $read = array_values(array_filter($log, static fn (array $e): bool => $e[0] === 'select' && str_contains($e[2], 'admin_boards')));
        $write = array_values(array_filter($log, static fn (array $e): bool => $e[0] === 'insert' && str_contains($e[2], 'admin_board_batches')));
        $this->assertSame($base + 1, $read[0][1] ?? null, 'the board is read inside the transaction (FOR UPDATE on MySQL and PostgreSQL)');
        $this->assertSame($base + 1, $write[0][1] ?? null);
        $this->assertLessThan(array_search($write[0], $log, true), array_search($read[0], $log, true));
    }

    public function test_the_database_refuses_a_second_batch_with_the_same_number(): void
    {
        $board = $this->makeBoard();
        (new BoardServer)->receive($board, 'a', 1, self::create('s1'));

        $this->expectException(UniqueConstraintViolationException::class);
        BoardBatch::query()->forceCreate(['board_id' => $board->id, 'seq' => 1, 'client' => 'b', 'cseq' => 1, 'ops' => '[]']);
    }

    /**
     * Two processes against one sqlite file: whatever the interleaving, the
     * numbers are unique and consecutive and every batch is in the board.
     */
    public function test_two_processes_receiving_at_once(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('pcntl/posix are not available');
        }
        $file = tempnam(sys_get_temp_dir(), 'board').'.sqlite';
        touch($file);
        config(['database.connections.race' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'busy_timeout' => 10_000, 'foreign_key_constraints' => true]]);
        $this->artisan('migrate', ['--database' => 'race', '--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true])->assertSuccessful();
        DB::setDefaultConnection('race');
        $board = Board::query()->create(['title' => 'Race']);
        DB::disconnect('race');

        $pids = [];
        foreach (['a', 'b'] as $client) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $result = 'ok';
                try {
                    DB::reconnect('race');
                    $server = new BoardServer;
                    for ($i = 1; $i <= 30; $i++) {
                        // sqlite answers "database is locked" where MySQL would wait for the row lock.
                        retry(50, fn () => $server->receive($board, $client, $i, self::create("{$client}{$i}")), 20);
                    }
                } catch (\Throwable $e) {
                    $result = $e->getMessage();
                }
                file_put_contents("{$file}.{$client}", $result);
                // Leave without running the parent's shutdown handlers.
                posix_kill(getmypid(), SIGKILL);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        foreach (['a', 'b'] as $client) {
            $this->assertSame('ok', @file_get_contents("{$file}.{$client}"), "writer {$client}");
            @unlink("{$file}.{$client}");
        }

        DB::reconnect('race');
        $this->assertSame(range(1, 60), BoardBatch::query()->where('board_id', $board->id)->orderBy('seq')->pluck('seq')->map(fn ($s) => (int) $s)->all());
        $this->assertSame(60, (int) Board::query()->whereKey($board->id)->value('seq'));
        $this->assertCount(60, (array) (new BoardServer)->snapshot($board)['board']->objects);
        DB::setDefaultConnection('testing');
        @unlink($file);
    }
}
