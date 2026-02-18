<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL to check if indexes exist before creating
        $this->createIndexIfNotExists('knowledge_chunks', 'content_hash', ['content_hash']);
        $this->createIndexIfNotExists('knowledge_chunks', 'source_url', ['source_url']);
        $this->createIndexIfNotExists('knowledge_chunks', 'scraped_at', ['scraped_at']);

        $this->createIndexIfNotExists('tasks', 'user_status', ['user_id', 'status']);
        $this->createIndexIfNotExists('tasks', 'case_status', ['case_id', 'status']);
        $this->createIndexIfNotExists('tasks', 'due_status', ['due_date', 'status']);
        $this->createIndexIfNotExists('tasks', 'type_status', ['task_type', 'status']);

        $this->createIndexIfNotExists('conversations', 'conv_case_created', ['case_id', 'created_at']);
        $this->createIndexIfNotExists('conversations', 'conv_user_created', ['user_id', 'created_at']);
        $this->createIndexIfNotExists('conversations', 'conv_role', ['role']);

        $this->createIndexIfNotExists('documents', 'doc_user_created', ['user_id', 'created_at']);
        $this->createIndexIfNotExists('documents', 'doc_case_created', ['case_id', 'created_at']);
        $this->createIndexIfNotExists('documents', 'doc_task_id', ['task_id']);
        $this->createIndexIfNotExists('documents', 'doc_type', ['document_type']);
        $this->createIndexIfNotExists('documents', 'doc_category', ['document_category']);
        $this->createIndexIfNotExists('documents', 'doc_processing', ['processing_status']);

        $this->createIndexIfNotExists('inbox_emails', 'user_received', ['user_id', 'received_at']);
        $this->createIndexIfNotExists('inbox_emails', 'account_received', ['email_account_id', 'received_at']);
        $this->createIndexIfNotExists('inbox_emails', 'relevant', ['is_relevant']);
    }

    private function createIndexIfNotExists(string $table, string $name, array $columns): void
    {
        $indexName = "{$table}_{$name}_index";

        // Check if index exists
        $exists = \DB::select("SELECT indexname FROM pg_indexes WHERE indexname = ?", [$indexName]);

        if (empty($exists)) {
            Schema::table($table, function (Blueprint $tbl) use ($name, $columns) {
                $tbl->index($columns, $name . '_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_chunks', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['content_hash']);
            $table->dropIndex(['source_url']);
            $table->dropIndex(['scraped_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['case_id', 'status']);
            $table->dropIndex(['due_date', 'status']);
            $table->dropIndex(['task_type', 'status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['case_id', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['role']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['case_id', 'created_at']);
            $table->dropIndex(['task_id']);
            $table->dropIndex(['document_type']);
            $table->dropIndex(['document_category']);
            $table->dropIndex(['processing_status']);
        });

        Schema::table('inbox_emails', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'received_at']);
            $table->dropIndex(['email_account_id', 'received_at']);
            $table->dropIndex(['is_relevant']);
        });
    }
};
