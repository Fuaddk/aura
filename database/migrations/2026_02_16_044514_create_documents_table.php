<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('file_size_bytes')->nullable();
            $table->text('storage_path');
            
            $table->string('document_type', 100)->nullable();
            $table->string('document_category', 50)->nullable();
            
            $table->string('processing_status', 50)->default('pending');
            $table->text('extracted_text')->nullable();
            
            $table->text('ai_summary')->nullable();
            $table->jsonb('ai_key_points')->nullable();
            $table->jsonb('ai_entities')->nullable();
            
            $table->boolean('encrypted')->default(true);
            $table->string('encryption_key_id')->nullable();
            
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            $table->index('case_id');
            $table->index('user_id');
            $table->index('processing_status');
            $table->index('document_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};