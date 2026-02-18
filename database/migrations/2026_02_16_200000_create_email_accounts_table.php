<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // gmail, outlook, other
            $table->string('email');
            $table->string('imap_host');
            $table->integer('imap_port')->default(993);
            $table->text('imap_password'); // encrypted via model cast
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('emails_found')->default(0);
            $table->integer('tasks_created')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};
