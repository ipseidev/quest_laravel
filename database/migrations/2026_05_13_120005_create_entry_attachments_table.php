<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entry_id')->constrained()->cascadeOnDelete();
            $table->string('uri', 2048)->default('');
            $table->string('remote_uri', 2048)->nullable();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps(3);

            $table->index(['is_deleted', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_attachments');
    }
};
