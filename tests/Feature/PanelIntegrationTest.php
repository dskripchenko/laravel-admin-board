<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Feature;

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdminBoard\AdminBoardPlugin;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Permissions;
use Dskripchenko\LaravelAdminBoard\Resources\BoardResource;
use Dskripchenko\LaravelAdminBoard\Support\BoardLayout;
use Dskripchenko\LaravelAdminBoard\Support\BoardUser;
use Dskripchenko\LaravelAdminBoard\Tests\TestCase;
use Dskripchenko\LaravelAdminBoard\Widgets\BoardWidget;

final class PanelIntegrationTest extends TestCase
{
    public function test_the_plugin_registers_its_resource_screen_and_permissions(): void
    {
        $this->assertContains(AdminBoardPlugin::class, (array) config('admin.plugins'));

        $admin = $this->app->make(Admin::class);
        $this->assertContains(BoardResource::class, $admin->getResources());
        $this->assertNotNull($admin->resolveScreen('board'));
        foreach ([Permissions::VIEW, Permissions::EDIT, Permissions::CREATE, Permissions::UPDATE, Permissions::DELETE] as $key) {
            $this->assertTrue($admin->getPermissionRegistry()->knows($key), $key);
        }
        $this->assertNotContains(BoardWidget::class, $admin->getWidgets(), 'no widget until a board is configured');
        $this->assertNotSame('', (new AdminBoardPlugin)->version());
    }

    public function test_the_captions_are_translated(): void
    {
        $this->app->setLocale('en');
        $this->assertSame('Boards', BoardResource::label());
        $this->app->setLocale('ru');
        $this->assertSame('Доски', BoardResource::label());
    }

    public function test_the_layout_carries_the_contract_props(): void
    {
        $editor = $this->actingAsAdmin(['name' => 'Olga'], [Permissions::VIEW, Permissions::EDIT]);
        $board = $this->makeBoard('Retro');

        $layout = BoardLayout::for($board);
        $array = $layout->toArray();

        $this->assertInstanceOf(\Dskripchenko\LaravelAdmin\Layout\Board::class, $layout);
        $this->assertSame('board', $array['type']);
        $this->assertSame('/boards/'.$board->id, $array['endpoint']);
        $this->assertFalse($array['readonly']);
        $this->assertSame(['id' => (string) $editor->getKey(), 'name' => 'Olga', 'color' => BoardUser::color((string) $editor->getKey())], $array['user']);
        $this->assertNull($array['realtime']);
        $this->assertSame(1500, $array['pollInterval']);
        $this->assertSame('Retro', $array['title']);
        $this->assertSame('640px', $array['height']);
    }

    public function test_the_colour_is_stable_per_user(): void
    {
        $this->assertSame(BoardUser::color('42'), BoardUser::color('42'));
        $this->assertContains(BoardUser::color('42'), BoardUser::COLORS);
        $colors = array_unique(array_map(static fn (int $i): string => BoardUser::color((string) $i), range(1, 50)));
        $this->assertGreaterThan(6, count($colors));
    }

    public function test_the_screen_shows_the_board(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $board = $this->makeBoard('Retro');

        $response = $this->getJson('/api/admin/board/state?id='.$board->id)->assertOk();

        $this->assertSame('Retro', $response->json('payload.name'));
        $this->assertTrue($response->json('payload.state.found'));
        $this->assertSame('board', $response->json('payload.layout.0.type'));
        $this->assertSame('/boards/'.$board->id, $response->json('payload.layout.0.endpoint'));
        $this->assertTrue($response->json('payload.layout.0.readonly'));

        $this->getJson('/api/admin/board/state?id=999')->assertOk()->assertJsonPath('payload.state.found', false)
            ->assertJsonPath('payload.layout.0.type', 'markdown');
    }

    public function test_the_screen_needs_the_view_permission(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::EDIT]);

        $this->getJson('/api/admin/board/state?id='.$this->makeBoard()->id)->assertForbidden();
    }

    public function test_the_resource_lists_and_creates_boards_without_their_content(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::CREATE]);
        $this->postResourceCreate('boards', ['title' => 'Roadmap'])->assertCreated();
        $this->postResourceCreate('boards', ['title' => ''])->assertStatus(422);

        $board = Board::query()->where('title', 'Roadmap')->firstOrFail();
        $this->assertSame(0, $board->seq);
        $this->assertSame('{"version":1,"objects":{}}', $board->board);

        $rows = $this->postResourceSearch('boards')->assertOk()->json('payload.data');
        $this->assertCount(1, $rows);
        $this->assertSame('Roadmap', $rows[0]['title']);
        $this->assertArrayNotHasKey('board', $rows[0]);
    }

    public function test_the_host_scope_narrows_the_list(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $this->makeBoard('Mine');
        $this->makeBoard('Theirs');
        \Dskripchenko\LaravelAdminBoard\Access\BoardAccess::scopeUsing(static fn ($q, object $user) => $q->where('title', 'Mine'));

        $this->assertSame(['Mine'], array_column($this->postResourceSearch('boards')->json('payload.data'), 'title'));
    }

    public function test_the_widget_shows_the_configured_board(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $board = $this->makeBoard('Team');

        $this->assertSame(['missing' => true], (new BoardWidget)->data());

        config(['admin-board.widget.board' => $board->id]);
        $widget = (new BoardWidget)->toArray();
        $this->assertSame('board', $widget['type']);
        $this->assertSame('/boards/'.$board->id, $widget['data']['endpoint']);
        $this->assertSame('100%', $widget['data']['height']);
        $this->assertTrue($widget['data']['readonly']);
    }
}
