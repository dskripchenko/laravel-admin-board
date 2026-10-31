<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Unit;

use Dskripchenko\LaravelAdminBoard\Sync\BoardOps;
use Dskripchenko\LaravelAdminBoard\Tests\Fixtures\Canon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The port of `applyOps` against the reference: the same board and the same
 * inverse operations for random boards and operations recorded from
 *
 * @dskripchenko/board (tests/Fixtures/reference/generate.mjs).
 */
final class ReferenceOpsTest extends TestCase
{
    /**
     * @return iterable<string, array{object}>
     */
    public static function cases(): iterable
    {
        foreach (Canon::reference()->applyOps as $case) {
            yield 'seed '.$case->seed => [$case];
        }
    }

    #[DataProvider('cases')]
    public function test_apply_ops_matches_the_reference(object $case): void
    {
        $board = BoardOps::decode((string) json_encode($case->board));
        $ops = BoardOps::normalize($case->ops);
        $this->assertNotNull($ops);

        $result = BoardOps::apply($board, $ops);

        $this->assertSame(Canon::of($case->expected->board), Canon::of(BoardOps::export($result['board'])));
        $this->assertSame(Canon::of($case->expected->inverse), Canon::of(BoardOps::exportOps($result['inverse'])));
    }

    public function test_the_fixtures_cover_every_kind_of_operation(): void
    {
        $kinds = [];
        foreach (Canon::reference()->applyOps as $case) {
            foreach ($case->ops as $op) {
                $kinds[$op->k] = true;
            }
        }
        ksort($kinds);

        $this->assertSame(['create', 'delete', 'meta', 'set'], array_keys($kinds));
        $this->assertGreaterThanOrEqual(200, count(Canon::reference()->applyOps));
    }

    public function test_the_empty_board(): void
    {
        $this->assertSame('{"objects":{},"version":1}', Canon::of(BoardOps::export(BoardOps::empty())));
        $this->assertSame('{"version":1,"objects":{}}', BoardOps::encode(BoardOps::empty()));
    }

    public function test_a_board_keyed_by_numbers_stays_an_object(): void
    {
        $board = BoardOps::apply(BoardOps::empty(), [
            ['k' => 'create', 'id' => '0', 'obj' => ['type' => 'text', '1' => 'x']],
            ['k' => 'meta', 'id' => BoardOps::META, 'props' => ['title' => 'T']],
        ])['board'];

        $this->assertSame('{"version":1,"objects":{"0":{"type":"text","1":"x","id":"0"}},"meta":{"title":"T"}}', BoardOps::encode($board));
    }

    public function test_malformed_operations_are_refused(): void
    {
        $decode = static fn (string $json): mixed => json_decode($json, false);

        $this->assertNull(BoardOps::normalize($decode('{"k":"set"}')));
        $this->assertNull(BoardOps::normalize($decode('[{"k":"move","id":"a"}]')));
        $this->assertNull(BoardOps::normalize($decode('[{"k":"create","id":"a"}]')));
        $this->assertNull(BoardOps::normalize($decode('[{"k":"set","id":"a","props":[1]}]')));
        $this->assertNull(BoardOps::normalize($decode('[{"k":"delete","id":5}]')));
        $this->assertSame([], BoardOps::normalize($decode('[]')));
    }
}
