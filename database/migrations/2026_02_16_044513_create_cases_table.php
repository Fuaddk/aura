<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->string('case_type', 50)->default('divorce');
            $table->string('status', 50)->default('active');
            
            $table->text('situation_summary')->nullable();
            $table->jsonb('situation_structured')->nullable();
            
            $table->date('separation_date')->nullable();
            $table->date('filing_date')->nullable();
            $table->date('expected_resolution_date')->nullable();
            
            $table->boolean('has_children')->default(false);
            $table->boolean('has_shared_property')->default(false);
            $table->boolean('has_shared_debt')->default(false);
            $table->smallInteger('complexity_score')->nullable();
            
            $table->jsonb('ai_context')->nullable();
            
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('case_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};