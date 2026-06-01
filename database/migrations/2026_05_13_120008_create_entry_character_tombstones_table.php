<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_character_tombstones', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('entry_id');
            $table->uuid('character_id');
            $table->timestamp('deleted_at', 3);

            $table->index(['user_id', 'deleted_at']);
            $table->index('deleted_at');
            $table->unique(['user_id', 'entry_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_character_tombstones');
    }
};
