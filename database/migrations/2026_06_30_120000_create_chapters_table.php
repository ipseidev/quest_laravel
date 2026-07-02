<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->timestamp('period_start', 3)->nullable();
            $table->timestamp('period_end', 3)->nullable();
            $table->foreignUuid('quest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('register', 20)->default('neutral');
            $table->text('title');
            $table->text('body');
            $table->text('threads');
            $table->string('status', 20)->default('ready');
            $table->timestamps(3);

            $table->index(['user_id', 'kind', 'period_start']);
            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
