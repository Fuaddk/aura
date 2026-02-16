<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('task_type', 100)->nullable();
            $table->string('priority', 20)->default('medium');
            
            $table->date('due_date')->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            
            $table->string('status', 50)->default('pending');
            $table->timestamp('completed_at')->nullable();
            
            $table->boolean('ai_generated')->default(false);
            $table->text('ai_reasoning')->nullable();
            $table->decimal('ai_confidence_score', 3, 2)->nullable();
            
            $table->foreignId('depends_on_task_id')
                ->nullable()
                ->constrained('tasks')
                ->onDelete('set null');
            
            $table->jsonb('metadata')->nullable();
            
            $table->timestamps();
            
            $table->index('case_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('due_date');
            $table->index('ai_generated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};