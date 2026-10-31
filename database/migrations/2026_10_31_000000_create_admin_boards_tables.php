<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_boards', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            // The record the board belongs to (HasBoard); null for a board of its own.
            $table->nullableMorphs('owner');
            // The board after every accepted batch, and the number of the last one.
            $table->json('board');
            $table->unsignedBigInteger('seq')->default(0);
            // A copy of meta.locked, for lists.
            $table->boolean('locked')->default(false);
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id']);
        });

        Schema::create('admin_board_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('board_id')->constrained('admin_boards')->cascadeOnDelete();
            $table->unsignedBigInteger('seq');
            $table->string('client', 64);
            $table->unsignedBigInteger('cseq');
            // Who sent it: the panel user's id.
            $table->string('user', 64)->nullable();
            $table->json('ops');
            $table->timestamp('created_at')->nullable();

            $table->unique(['board_id', 'seq']);
            $table->unique(['board_id', 'client', 'cseq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_board_batches');
        Schema::dropIfExists('admin_boards');
    }
};
