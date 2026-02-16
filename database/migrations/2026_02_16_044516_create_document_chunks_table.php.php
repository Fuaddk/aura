<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('case_id')->constrained('cases')->onDelete('cascade');
            
            $table->integer('chunk_index');
            $table->text('chunk_text');
            $table->integer('chunk_size')->nullable();
            
            $table->jsonb('metadata')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('document_id');
            $table->index('case_id');
        });
        
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1536)');
        DB::statement('CREATE INDEX ON document_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};