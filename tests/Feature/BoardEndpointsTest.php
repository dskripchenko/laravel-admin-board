<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Feature;

use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Permissions;
use Dskripchenko\LaravelAdminBoard\Sync\BoardOps;
use Dskripchenko\LaravelAdminBoard\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class BoardEndpointsTest extends TestCase
{
    public function test_the_routes_sit_under_the_panel_api(): void
    {
        foreach (['snapshot', 'batches', 'poll', 'presence'] as $action) {
            $route = Route::getRoutes()->getByName('admin.boards.'.$action);
            $this->assertNotNull($route, $action);
            $this->assertSame('api/admin/boards/{board}/'.$action, $route->uri());
            $this->assertSame(['POST'], $route->methods());
            $this->assertSame('admin', $route->defaults['adminPanel']);
            $this->assertContains('api-middleware-group', $route->gatherMiddleware());
        }
    }

    public function test_the_board_routes_take_their_own_request_limit_instead_of_the_panels(): void
    {
        // The panel's limit, AdminApi::getMethods()['middleware'], is shared by
        // the whole panel: a board's cursor reports would spend it.
        config(['admin.api.throttle' => '2,1', 'admin-board.throttle' => '4,1']);
        \Dskripchenko\LaravelAdmin\Http\AdminApi::clearCache();
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $board = $this->makeBoard();

        for ($i = 0; $i < 4; $i++) {
            $this->boardApi($board, 'presence', ['client' => 'a'])->assertOk();
        }
        $this->boardApi($board, 'snapshot')->assertStatus(429);
    }

    public function test_the_board_request_limit_can_be_turned_off(): void
    {
        config(['admin.api.throttle' => '1,1', 'admin-board.throttle' => null]);
        \Dskripchenko\LaravelAdmin\Http\AdminApi::clearCache();
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $board = $this->makeBoard();

        for ($i = 0; $i < 3; $i++) {
            $this->boardApi($board, 'snapshot')->assertOk();
        }
    }

    public function test_a_guest_gets_401_everywhere(): void
    {
        $board = $this->makeBoard();

        foreach (['snapshot', 'batches', 'poll', 'presence'] as $action) {
            $this->boardApi($board, $action, ['client' => 'a', 'cseq' => 1, 'ops' => [], 'after' => 0])
                ->assertUnauthorized()->assertJsonPath('success', false);
        }
    }

    public function test_a_user_without_the_view_permission_gets_403(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::EDIT]);
        $board = $this->makeBoard();

        $this->boardApi($board, 'snapshot')->assertForbidden();
        $this->boardApi($board, 'poll', ['after' => 0])->assertForbidden();
        $this->boardApi($board, 'presence', ['client' => 'a'])->assertForbidden();
        $this->sendBatch($board, 'a', 1, [])->assertForbidden();
    }

    public function test_a_viewer_reads_and_reports_presence_but_may_not_send_batches(): void
    {
        $this->actingAsAdmin(['name' => 'Vera'], [Permissions::VIEW]);
        $board = $this->makeBoard();

        $this->boardApi($board, 'snapshot')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payload.seq', 0)
            ->assertJsonPath('payload.board.version', 1);
        $this->assertSame('{"version":1,"objects":{}}', json_encode($this->boardApi($board, 'snapshot')->json('payload.board'), JSON_FORCE_OBJECT));
        $this->boardApi($board, 'poll', ['after' => 0])->assertOk()->assertJsonPath('payload.batches', []);
        $this->boardApi($board, 'presence', ['client' => 'tab-1'])->assertOk()->assertJsonPath('payload.presence.0.name', 'Vera');

        $this->sendBatch($board, 'tab-1', 1, [['k' => 'create', 'id' => 's1', 'obj' => self::sticky()]])
            ->assertForbidden()->assertJsonPath('payload.errorKey', 'forbidden');
        $this->assertSame(0, $board->refresh()->seq);
    }

    public function test_an_editor_sends_batches_and_others_poll_them(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::EDIT]);
        $board = $this->makeBoard();

        $this->sendBatch($board, 'tab-1', 1, [['k' => 'create', 'id' => 's1', 'obj' => self::sticky()]])
            ->assertOk()->assertExactJson(['success' => true, 'payload' => ['seq' => 1]]);
        $this->sendBatch($board, 'tab-1', 1, [['k' => 'create', 'id' => 's1', 'obj' => self::sticky()]])
            ->assertOk()->assertJsonPath('payload.seq', 1);
        $this->sendBatch($board, 'tab-2', 1, [['k' => 'set', 'id' => 's1', 'props' => ['text' => 'B']]])
            ->assertOk()->assertJsonPath('payload.seq', 2);

        $batches = $this->boardApi($board, 'poll', ['after' => 0])->assertOk()->json('payload.batches');
        $this->assertSame([1, 2], array_column($batches, 'seq'));
        $this->assertSame('tab-2', $batches[1]['client']);
        $this->assertSame((string) auth('admin')->id(), $batches[1]['user']);
        $this->boardApi($board, 'snapshot')->assertJsonPath('payload.seq', 2)->assertJsonPath('payload.board.objects.s1.text', 'B');
    }

    public function test_422_for_a_batch_over_the_limits_or_malformed(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::EDIT]);
        $board = $this->makeBoard();

        $this->sendBatch($board, 'a', 1, array_fill(0, 501, ['k' => 'delete', 'id' => 'x']))
            ->assertStatus(422)->assertJsonPath('payload.errorKey', 'batch_rejected');
        config(['admin-board.limits.max_objects' => 1]);
        $this->sendBatch($board, 'a', 1, [['k' => 'create', 'id' => 'a', 'obj' => self::sticky()], ['k' => 'create', 'id' => 'b', 'obj' => self::sticky()]])
            ->assertStatus(422);
        $this->sendBatch($board, 'a', 1, [['k' => 'teleport', 'id' => 'a']])->assertStatus(422);
        $this->boardApi($board, 'batches', ['client' => 'a', 'ops' => []])->assertStatus(422);
        $this->boardApi($board, 'batches', ['client' => 'a', 'cseq' => '1', 'ops' => []])->assertStatus(422);
        $this->assertSame(0, $board->refresh()->seq);
    }

    public function test_410_when_the_poll_is_older_than_the_log(): void
    {
        config(['admin-board.keep' => 2]);
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::EDIT]);
        $board = $this->makeBoard();
        for ($i = 1; $i <= 4; $i++) {
            $this->sendBatch($board, 'a', $i, [['k' => 'meta', 'id' => BoardOps::META, 'props' => ['title' => "v{$i}"]]])->assertOk();
        }

        $this->boardApi($board, 'poll', ['after' => 1])->assertStatus(410)->assertJsonPath('payload.errorKey', 'too_old');
        $this->boardApi($board, 'poll', ['after' => 2])->assertOk()->assertJsonCount(2, 'payload.batches');
        $this->boardApi($board, 'poll', ['after' => 'x'])->assertStatus(422);
    }

    public function test_404_for_a_missing_board(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);

        $this->postJson('/api/admin/boards/999/snapshot')->assertNotFound()->assertJsonPath('payload.errorKey', 'not_found');
    }

    public function test_the_host_hook_narrows_access_per_board(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::EDIT]);
        $open = $this->makeBoard('Open');
        $closed = $this->makeBoard('Closed');
        $readonly = $this->makeBoard('Read-only');
        BoardAccess::authorizeUsing(static fn (object $user, Board $board, string $ability): ?bool => match ($board->title) {
            'Closed' => false,
            'Read-only' => $ability === 'view',
            default => null,
        });

        $this->boardApi($open, 'snapshot')->assertOk();
        $this->boardApi($closed, 'snapshot')->assertForbidden();
        $this->boardApi($readonly, 'snapshot')->assertOk();
        $this->sendBatch($readonly, 'a', 1, [])->assertForbidden();
        $this->sendBatch($open, 'a', 1, [])->assertOk();
    }

    public function test_presence_lists_everyone_seen_within_30_seconds(): void
    {
        $this->actingAsAdmin(['name' => 'Olga'], [Permissions::VIEW]);
        $board = $this->makeBoard();

        $this->boardApi($board, 'presence', ['client' => 'tab-1', 'cursor' => ['x' => 10, 'y' => 20.5], 'selection' => ['s1', 5]])->assertOk();
        $this->travel(20)->seconds();
        $presence = $this->boardApi($board, 'presence', ['client' => 'tab-2', 'user' => ['id' => 'spoofed'], 'cursor' => null])
            ->assertOk()->json('payload.presence');

        $this->assertSame(['tab-1', 'tab-2'], array_column($presence, 'client'));
        $this->assertSame(['x' => 10, 'y' => 20.5], $presence[0]['cursor']);
        $this->assertSame(['s1'], $presence[0]['selection']);
        $this->assertNull($presence[1]['cursor']);
        $this->assertSame((string) auth('admin')->id(), $presence[1]['user'], 'who it is comes from the session');
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $presence[1]['color']);
        $this->assertIsInt($presence[1]['at']);

        $this->travel(15)->seconds();
        $presence = $this->boardApi($board, 'presence', ['client' => 'tab-2'])->json('payload.presence');
        $this->assertSame(['tab-2'], array_column($presence, 'client'), 'tab-1 was last seen 35 s ago');

        $this->travel(31)->seconds();
        $this->assertNull(cache()->get('admin-board:presence:'.$board->id), 'the cache entry expires too');
    }

    public function test_presence_needs_a_client(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);

        $this->boardApi($this->makeBoard(), 'presence', ['client' => ''])->assertStatus(422);
    }
}
