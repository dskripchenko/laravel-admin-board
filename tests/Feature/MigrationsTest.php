<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Feature;

use Dskripchenko\LaravelAdminBoard\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MigrationsTest extends TestCase
{
    public function test_the_tables_exist_on_sqlite(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertTrue(Schema::hasColumns('admin_boards', ['id', 'title', 'owner_type', 'owner_id', 'board', 'seq', 'locked', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('admin_board_batches', ['id', 'board_id', 'seq', 'client', 'cseq', 'user', 'ops', 'created_at']));
        $this->assertFalse(Schema::hasColumn('admin_board_batches', 'updated_at'));
    }

    public function test_the_migrations_roll_back_and_run_again(): void
    {
        $path = realpath(__DIR__.'/../../database/migrations');
        Artisan::call('migrate:rollback', ['--path' => $path, '--realpath' => true]);
        $this->assertFalse(Schema::hasTable('admin_boards'));
        $this->assertFalse(Schema::hasTable('admin_board_batches'));

        Artisan::call('migrate', ['--path' => $path, '--realpath' => true]);
        $this->assertTrue(Schema::hasTable('admin_boards'));
        $this->assertTrue(Schema::hasTable('admin_board_batches'));
    }

    public function test_deleting_a_board_removes_its_batches(): void
    {
        $board = $this->makeBoard();
        DB::table('admin_board_batches')->insert(['board_id' => $board->id, 'seq' => 1, 'client' => 'a', 'cseq' => 1, 'ops' => '[]']);

        $board->delete();

        $this->assertSame(0, DB::table('admin_board_batches')->count());
    }
}
