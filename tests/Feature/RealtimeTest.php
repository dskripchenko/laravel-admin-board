<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Feature;

use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Permissions;
use Dskripchenko\LaravelAdminBoard\Realtime\BoardPublisher;
use Dskripchenko\LaravelAdminBoard\Realtime\BoardRealtime;
use Dskripchenko\LaravelAdminBoard\Realtime\RealtimePublisher;
use Dskripchenko\LaravelAdminBoard\Support\BoardLayout;
use Dskripchenko\LaravelAdminBoard\Tests\Fixtures\FakePublisher;
use Dskripchenko\LaravelAdminBoard\Tests\TestCase;

final class RealtimeTest extends TestCase
{
    protected function defineAdditionalEnvironment($app): void
    {
        parent::defineAdditionalEnvironment($app);
        $app->singleton(BoardPublisher::class, FakePublisher::class);
    }

    private function publisher(): FakePublisher
    {
        /** @var FakePublisher */
        return $this->app->make(BoardPublisher::class);
    }

    public function test_laravel_realtime_is_not_installed_here(): void
    {
        $this->assertFalse(RealtimePublisher::available(), 'the suite runs without the optional package');
    }

    public function test_a_fresh_batch_and_presence_are_published_on_the_board_channel(): void
    {
        $this->actingAsAdmin(['name' => 'Olga'], [Permissions::VIEW, Permissions::EDIT]);
        $board = $this->makeBoard();

        $this->sendBatch($board, 'tab-1', 1, [['k' => 'create', 'id' => 's1', 'obj' => self::sticky()]])->assertOk();
        $this->sendBatch($board, 'tab-1', 1, [['k' => 'create', 'id' => 's1', 'obj' => self::sticky()]])->assertOk();
        $this->boardApi($board, 'presence', ['client' => 'tab-1'])->assertOk();

        $published = $this->publisher()->published;
        $this->assertCount(2, $published, 'a resend is not published again');
        $this->assertSame(['admin-board.'.$board->id, 'batch'], [$published[0]['channel'], $published[0]['event']]);
        $this->assertSame(1, $published[0]['data']['seq']);
        $this->assertSame('tab-1', $published[0]['data']['client']);
        $this->assertSame('s1', $published[0]['data']['ops'][0]['id']);
        $this->assertSame('presence', $published[1]['event']);
        $this->assertSame('Olga', $published[1]['data'][0]['name']);
    }

    public function test_nothing_is_published_when_turned_off(): void
    {
        config(['admin-board.realtime.enabled' => false]);
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::EDIT]);
        $board = $this->makeBoard();

        $this->sendBatch($board, 'tab-1', 1, [])->assertOk();
        $this->boardApi($board, 'presence', ['client' => 'tab-1'])->assertOk();

        $this->assertSame([], $this->publisher()->published);
        $this->assertNull(BoardLayout::props($board)['realtime']);
    }

    public function test_the_layout_carries_the_ticket_url_and_channel(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $board = $this->makeBoard();

        $this->assertSame(['ticketUrl' => '/realtime/ticket', 'channel' => 'admin-board.'.$board->id], BoardLayout::props($board)['realtime']);
        config(['admin-board.realtime.ticket_url' => '/ws/ticket']);
        $this->assertSame('/ws/ticket', BoardLayout::props($board)['realtime']['ticketUrl'] ?? null);
    }

    public function test_the_channel_is_authorized_by_the_view_check(): void
    {
        $authorize = $this->publisher()->channels['admin-board.{id}'] ?? null;
        $this->assertIsCallable($authorize);
        $board = $this->makeBoard();
        $viewer = $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $stranger = $this->actingAsAdmin(permissions: []);

        $this->assertTrue($authorize($viewer, (string) $board->id));
        $this->assertFalse($authorize($stranger, (string) $board->id));
        $this->assertFalse($authorize($viewer, '999'));
        $this->assertFalse($authorize($viewer, 'abc'));

        BoardAccess::authorizeUsing(static fn (object $user, Board $b): bool => false);
        $this->assertFalse($authorize($viewer, (string) $board->id));
    }

    public function test_a_failing_broker_does_not_fail_the_request(): void
    {
        $this->app->instance(BoardPublisher::class, new class implements BoardPublisher
        {
            public function publish(string $channel, string $event, mixed $data): void
            {
                throw new \RuntimeException('broker down');
            }

            public function authorizeChannel(string $pattern, callable $callback): void {}
        });
        $this->app->forgetInstance(BoardRealtime::class);
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::EDIT]);
        $board = $this->makeBoard();

        $this->sendBatch($board, 'tab-1', 1, [])->assertOk()->assertJsonPath('payload.seq', 1);
    }
}
