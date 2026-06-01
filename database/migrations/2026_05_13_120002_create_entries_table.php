<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('title');
            $table->text('html');
            $table->string('mood', 50)->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->timestamp('entry_date', 3)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps(3);

            $table->index(['user_id', 'entry_date']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'is_deleted', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
