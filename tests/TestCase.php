<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests;

use Dskripchenko\LaravelAdmin\Testing\Concerns\ActsAsAdmin;
use Dskripchenko\LaravelAdmin\Testing\Concerns\InteractsWithAdminResources;
use Dskripchenko\LaravelAdmin\Testing\PackageTestCase;
use Dskripchenko\LaravelAdminBoard\Access\BoardAccess;
use Dskripchenko\LaravelAdminBoard\AdminBoardServiceProvider;
use Dskripchenko\LaravelAdminBoard\Models\Board;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends PackageTestCase
{
    use ActsAsAdmin;
    use InteractsWithAdminResources;

    protected function tearDown(): void
    {
        BoardAccess::authorizeUsing(null);
        BoardAccess::scopeUsing(null);
        parent::tearDown();
    }

    protected function additionalProviders(): array
    {
        return [AdminBoardServiceProvider::class];
    }

    protected function defineAdditionalEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
    }

    protected function makeBoard(string $title = 'Plan'): Board
    {
        return Board::query()->create(['title' => $title]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function boardApi(Board $board, string $action, array $body = []): TestResponse
    {
        return $this->postJson('/api/admin/boards/'.$board->id.'/'.$action, $body);
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     */
    protected function sendBatch(Board $board, string $client, int $cseq, array $ops): TestResponse
    {
        return $this->boardApi($board, 'batches', ['client' => $client, 'cseq' => $cseq, 'ops' => $ops]);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function sticky(string $text = 'Hi'): array
    {
        return ['type' => 'sticky', 'x' => 0, 'y' => 0, 'w' => 200, 'h' => 200, 'z' => 'a0', 'color' => 'yellow', 'text' => $text];
    }
}
