<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('source_url');
            $table->string('source_title')->nullable();
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->string('category')->nullable(); // separation, samvaer, bodeling, vold, etc.
            $table->integer('chunk_index')->default(0);
            $table->integer('token_count')->default(0);
            $table->string('content_hash', 64); // SHA-256 to detect changes
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->index('source_url');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
