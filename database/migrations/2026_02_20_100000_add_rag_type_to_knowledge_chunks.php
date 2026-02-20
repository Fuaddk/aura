<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_chunks', function (Blueprint $table) {
            $table->string('rag_type', 30)->default('knowledge')->after('category');
            $table->string('phase_tag', 30)->nullable()->after('rag_type');
            $table->string('task_type_tag', 30)->nullable()->after('phase_tag');

            $table->index('rag_type');
            $table->index(['rag_type', 'phase_tag']);
            $table->index(['rag_type', 'task_type_tag']);
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_chunks', function (Blueprint $table) {
            $table->dropIndex(['rag_type']);
            $table->dropIndex(['rag_type', 'phase_tag']);
            $table->dropIndex(['rag_type', 'task_type_tag']);
            $table->dropColumn(['rag_type', 'phase_tag', 'task_type_tag']);
        });
    }
};
