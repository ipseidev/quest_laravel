<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->text('relationship')->nullable();
            $table->text('note');
            $table->string('photo_uri', 2048)->default('');
            $table->string('remote_photo_uri', 2048)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps(3);

            $table->index(['user_id', 'is_deleted', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
