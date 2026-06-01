<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_characters', function (Blueprint $table) {
            $table->foreignUuid('entry_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('character_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at', 3);

            $table->primary(['entry_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_characters');
    }
};
