<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('message_uid');
            $table->string('subject')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('snippet')->nullable();
            $table->boolean('is_relevant')->default(false);
            $table->json('analysis_result')->nullable(); // AI analysis JSON
            $table->integer('tasks_created')->default(0);
            $table->unique(['email_account_id', 'message_uid']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_emails');
    }
};
