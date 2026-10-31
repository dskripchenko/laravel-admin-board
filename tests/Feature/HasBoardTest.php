<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminBoard\Tests\Feature;

use Dskripchenko\LaravelAdminBoard\Models\Board;
use Dskripchenko\LaravelAdminBoard\Permissions;
use Dskripchenko\LaravelAdminBoard\Tests\Fixtures\Project;
use Dskripchenko\LaravelAdminBoard\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

final class HasBoardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function test_the_board_is_created_on_first_access_and_only_once(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $project = Project::query()->create(['name' => 'Apollo']);
        $this->assertNull($project->board()->first());

        $first = $project->boardLayout()->toArray();
        $second = Project::query()->findOrFail($project->id)->boardLayout()->toArray();

        $this->assertSame(1, Board::query()->count());
        $board = $project->board()->firstOrFail();
        $this->assertSame('Board of Apollo', $board->title);
        $this->assertSame(Project::class, $board->owner_type);
        $this->assertTrue($board->owner?->is($project));
        $this->assertSame('board', $first['type']);
        $this->assertSame('/boards/'.$board->id, $first['endpoint']);
        $this->assertSame($first['endpoint'], $second['endpoint']);
        $this->assertTrue($first['readonly'], 'a viewer without edit');
    }

    public function test_each_record_gets_its_own_board(): void
    {
        $a = Project::query()->create(['name' => 'A']);
        $b = Project::query()->create(['name' => 'B']);

        $this->assertNotSame($a->boardModel()->id, $b->boardModel()->id);
        $this->assertSame($a->boardModel()->id, $a->boardModel()->id);
    }

    public function test_the_database_keeps_one_board_per_record(): void
    {
        $project = Project::query()->create(['name' => 'A']);
        $project->boardModel();

        $this->expectException(UniqueConstraintViolationException::class);
        $project->board()->create(['title' => 'Second']);
    }
}
