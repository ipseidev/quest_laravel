<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->text('title');
            $table->text('description');
            $table->string('status', 15)->default('active');
            $table->string('color', 20)->nullable();
            $table->string('icon', 20)->nullable();
            $table->timestamp('started_at', 3)->nullable();
            $table->timestamp('completed_at', 3)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps(3);

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type', 'status']);
            $table->index(['user_id', 'is_deleted', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quests');
    }
};
