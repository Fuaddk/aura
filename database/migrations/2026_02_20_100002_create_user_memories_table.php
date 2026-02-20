<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->string('category', 40)->default('general');
            $table->string('content_hash', 64);
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_memories');
    }
};
